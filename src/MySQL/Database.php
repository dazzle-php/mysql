<?php

namespace Dazzle\MySQL;

use Dazzle\Event\BaseEventEmitter;
use Dazzle\Loop\LoopAwareTrait;
use Dazzle\Loop\LoopInterface;
use Dazzle\MySQL\Command\Command;
use Dazzle\MySQL\Command\CommandInterface;
use Dazzle\MySQL\Command\Concrete\AuthCommand;
use Dazzle\MySQL\Command\Concrete\PingCommand;
use Dazzle\MySQL\Command\Concrete\QueryCommand;
use Dazzle\MySQL\Command\Concrete\QuitCommand;
use Dazzle\MySQL\Protocol\ProtocolParser;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;
use Dazzle\Socket\Socket;
use Dazzle\Socket\SocketInterface;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class Database extends BaseEventEmitter implements DatabaseInterface
{
    use LoopAwareTrait;

    const STATE_INIT                = 0;
    const STATE_CONNECT_PENDING     = 3;
    const STATE_CONNECT_FAILED      = 1;
    const STATE_CONNECT_SUCCEEDED   = 4;
    const STATE_AUTH_PENDING        = 8;
    const STATE_AUTH_FAILED         = 2;
    const STATE_AUTH_SUCCEEDED      = 5;
    const STATE_CLOSEING            = 6;
    const STATE_STOPPED             = 7;

    protected $config;

    protected $serverOptions;

    protected $executor;

    protected $state = self::STATE_INIT;

    protected $stream;

    protected $parser;

    /**
     * @param LoopInterface $loop
     * @param mixed[] $config
     */
    public function __construct(LoopInterface $loop, $config = [])
    {
        $this->loop = $loop;
        $this->executor = $this->createExecutor();
        $this->config = $this->createConfig($config);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isPaused()
    {
        // TODO
    }

    /**
     * @override
     * @inheritDoc
     */
    public function pause()
    {
        // TODO
    }

    /**
     * @override
     * @inheritDoc
     */
    public function resume()
    {
        // TODO
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isStarted()
    {
        // TODO
    }

    /**
     * @override
     * @inheritDoc
     */
    public function start()
    {
        $this->state = self::STATE_CONNECT_PENDING;
        $options = $this->config;
        $streamRef = $this->stream;
        $args = func_get_args();

        if (!$args) {
            throw new Exception('Not Implemented');
        }

        $errorHandler = function ($reason) use ($args) {
            $this->state = self::STATE_AUTH_FAILED;
            $args[0]($reason, $this);
        };

        $connectedHandler = function ($serverOptions) use ($args) {
            $this->state = self::STATE_AUTH_SUCCEEDED;
            $this->serverOptions = $serverOptions;
            $args[0](null, $this);
        };

        $this
            ->connect()
            ->then(function ($stream) use (&$streamRef, $options, $errorHandler, $connectedHandler) {
                $streamRef = $stream;

                $stream->on('error', [ $this, 'handleConnectionError' ]);
                $stream->on('close', [ $this, 'handleConnectionClosed' ]);

                $parser = $this->parser = new ProtocolParser($stream, $this->executor);

                $parser->setOptions($options);

                $command = $this->doCommand(new AuthCommand($this));
                $command->on('authenticated', $connectedHandler);
                $command->on('error', $errorHandler);

                //$parser->on('close', $closeHandler);
                $parser->start();

            }, [ $this, 'handleConnectionError' ]);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function stop()
    {
        $this
            ->doCommand(new QuitCommand($this))
            ->on('success', function() {
                $this->state = self::STATE_STOPPED;
                $this->emit('end', [ $this ]);
                $this->emit('close', [ $this ]);
            });
        $this->state = self::STATE_CLOSEING;
    }

    /**
     * Do a async query.
     */
    public function query()
    {
        $numArgs = func_num_args();

        if ($numArgs === 0)
        {
            throw new InvalidArgumentException('Required at least 1 argument');
        }

        $args = func_get_args();
        $query = new Query(array_shift($args));

        $callback = array_pop($args);

        $command = new QueryCommand($this);
        $command->setQuery($query);

        if (!is_callable($callback))
        {
            if ($numArgs > 1)
            {
                $args[] = $callback;
            }
            $query->bindParamsFromArray($args);

            return $this->doCommand($command);
        }

        $query->bindParamsFromArray($args);
        $this->doCommand($command);

        $command->on('results', function ($rows, $command) use ($callback) {
            $callback($command, $this);
        });
        $command->on('error', function ($err, $command) use ($callback) {
            $callback($command, $this);
        });
        $command->on('success', function ($command) use ($callback) {
            $callback($command, $this);
        });
    }

    public function ping($callback)
    {
        if (!is_callable($callback))
        {
            throw new InvalidArgumentException('Callback is not a valid callable');
        }
        $this->doCommand(new PingCommand($this))
            ->on('error', function ($reason) use ($callback) {
                $callback($reason, $this);
            })
            ->on('success', function () use ($callback) {
                $callback(null, $this);
            });
    }

    public function selectDB($dbname)
    {
        return $this->query(sprintf('USE `%s`', $dbname));
    }

    public function setOption($name, $value)
    {
        $this->config[$name] = $value;

        return $this;
    }

    public function getOption($name, $default = null)
    {
        if (isset($this->config[$name]))
        {
            return $this->config[$name];
        }

        return $default;
    }

    public function getState()
    {
        return $this->state;
    }

    public function handleConnectionError($err)
    {
        $this->emit('error', [ $err, $this ]);
    }

    public function handleConnectionClosed()
    {
        if ($this->state < self::STATE_CLOSEING)
        {
            $this->state = self::STATE_STOPPED;
            $this->emit('error', [ new RuntimeException('mysql server has gone away'), $this ]);
        }
    }

    protected function doCommand(CommandInterface $command)
    {
        if ($command->equals(Command::INIT_AUTHENTICATE))
        {
            return $this->executor->undequeue($command);
        }
        elseif ($this->state >= self::STATE_CONNECT_PENDING && $this->state <= self::STATE_AUTH_SUCCEEDED)
        {
            return $this->executor->enqueue($command);
        }
        else
        {
            throw new Exception("Cann't send command");
        }
    }

    public function getServerOptions()
    {
        return $this->serverOptions;
    }

    protected function connect()
    {
        $socket = new Socket($this->config['endpoint'], $this->getLoop());
        return Promise::doResolve($socket);
    }

    /**
     * Create executor.
     *
     * @return Executor
     */
    protected function createExecutor()
    {
        return new Executor($this);
    }

    /**
     * Create configuration file.
     *
     * @param mixed[] $config
     * @return mixed[]
     */
    protected function createConfig($config = [])
    {
        $default = [
            'endpoint' => 'tcp://127.0.0.1:3306',
            'user'     => 'root',
            'pass'     => '',
            'dbname'   => '',
        ];
        return array_merge($default, $config);
    }
}
