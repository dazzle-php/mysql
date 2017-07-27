<?php

namespace Dazzle\MySQL;

use Dazzle\Event\BaseEventEmitter;
use Dazzle\Loop\LoopAwareTrait;
use Dazzle\Loop\LoopInterface;
use Dazzle\MySQL\Protocol\Command\AuthCommand;
use Dazzle\MySQL\Protocol\Command\PingCommand;
use Dazzle\MySQL\Protocol\Command\QueryCommand;
use Dazzle\MySQL\Protocol\Command\QuitCommand;
use Dazzle\MySQL\Protocol\Command;
use Dazzle\MySQL\Protocol\CommandInterface;
use Dazzle\MySQL\Protocol\ProtocolParser;
use Dazzle\MySQL\Support\Executor\Executor;
use Dazzle\MySQL\Support\Transaction\TransactionBox;
use Dazzle\MySQL\Support\Transaction\TransactionBoxInterface;
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

    protected $state;

    protected $mode;

    protected $executor;

    protected $parser;

    protected $stream;

    protected $trans;

    /**
     * @param LoopInterface $loop
     * @param mixed[] $config
     */
    public function __construct(LoopInterface $loop, $config = [])
    {
        $this->loop = $loop;
        $this->config = $this->createConfig($config);
        $this->serverOptions = [];
        $this->state = self::STATE_INIT;
        $this->executor = $this->createExecutor();
        $this->parser = null;
        $this->stream = null;
        $this->trans = $this->createTransactionBox();
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
        return new Promise(function($resolve, $reject) {

            $this->state = self::STATE_CONNECT_PENDING;
            $options = $this->config;
            $streamRef = $this->stream;

            $errorHandler = function ($reason) use ($reject) {
                $this->state = self::STATE_AUTH_FAILED;
                return $reject($reason);
            };

            $connectedHandler = function ($serverOptions) use ($resolve) {
                $this->state = self::STATE_AUTH_SUCCEEDED;
                $this->serverOptions = $serverOptions;
                return $resolve($serverOptions);
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
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function stop()
    {
        return new Promise(function($resolve, $reject) {
            $this
                ->doCommand(new QuitCommand($this))
                ->on('success', function() use($resolve) {
                    $this->state = self::STATE_STOPPED;
                    $this->emit('end', [ $this ]);
                    $this->emit('close', [ $this ]);
                    $resolve($this);
                });
            $this->state = self::STATE_CLOSEING;
        });
    }

    /**
     * Do a async query.
     *
     * @param string $sql
     * @param mixed[] $sqlParams
     * @return PromiseInterface
     */
    public function query($sql, $sqlParams = [])
    {
        $promise = new Promise();
        $query   = new Query($sql);
        $command = new QueryCommand($this);

        $command->setQuery($query);
        $query->bindParamsFromArray($sqlParams);

        $this->doCommand($command);

        $command->on('results', function ($rows, $command) use ($promise) {
            return $command->hasError() ? $promise->reject($command->getError()) : $promise->resolve($command);
        });
        $command->on('error', function ($err, $command) use ($promise) {
            return $promise->reject($err);
        });
        $command->on('success', function ($command) use ($promise) {
            return $command->hasError() ? $promise->reject($command->getError()) : $promise->resolve($command);
        });

        return $promise;
    }

    public function execute($sql, $sqlParams = [])
    {
        // TODO
    }

    public function ping()
    {
        $promise = new Promise();

        $this->doCommand(new PingCommand($this))
            ->on('error', function ($reason) use ($promise) {
                return $promise->reject($reason);
            })
            ->on('success', function () use ($promise) {
                return $promise->resolve();
            });
    }

    public function beginTransaction()
    {
        return $this->trans->enqueue(new Transaction($this));
    }

    public function endTransaction(TransactionInterface $trans)
    {
        // TODO
    }

    public function inTransaction()
    {
        return !$this->trans->isEmpty();
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
     * Create transaction box.
     *
     * @return TransactionBoxInterface
     */
    protected function createTransactionBox()
    {
        return new TransactionBox();
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
