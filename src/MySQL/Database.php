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
use Dazzle\MySQL\Protocol\Query;
use Dazzle\MySQL\Protocol\QueryInterface;
use Dazzle\MySQL\Support\Queue\Queue;
use Dazzle\MySQL\Support\Queue\QueueInterface;
use Dazzle\MySQL\Support\Transaction\TransactionBox;
use Dazzle\MySQL\Support\Transaction\TransactionBoxInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;
use Dazzle\Socket\Socket;
use Dazzle\Socket\SocketInterface;
use Dazzle\Throwable\Exception\Runtime\ExecutionException;
use RuntimeException;
use SplQueue;

class Database extends BaseEventEmitter implements DatabaseInterface
{
    use LoopAwareTrait;

    /**
     * @var int
     */
    const STATE_INIT                 = 0;

    /**
     * @var int
     */
    const STATE_CONNECT_PENDING      = 4;

    /**
     * @var int
     */
    const STATE_CONNECT_FAILED       = 2;

    /**
     * @var int
     */
    const STATE_CONNECT_SUCCEEDED    = 6;

    /**
     * @var int
     */
    const STATE_AUTH_PENDING         = 5;

    /**
     * @var int
     */
    const STATE_AUTH_FAILED          = 3;

    /**
     * @var int
     */
    const STATE_AUTH_SUCCEEDED       = 7;

    /**
     * @var int
     */
    const STATE_DISCONNECT_PENDING   = 8;

    /**
     * @var int
     */
    const STATE_DISCONNECT_SUCCEEDED = 1;

    /**
     * @var mixed[]
     */
    protected $config;

    /**
     * @var mixed[]
     */
    protected $serverInfo;

    /**
     * @var int
     */
    protected $state;

    /**
     * @var Queue|QueueInterface
     */
    protected $queue;

    /**
     * @var ProtocolParser|null
     */
    protected $parser;

    /**
     * @var SocketInterface|null
     */
    protected $stream;

    /**
     * @var TransactionBoxInterface
     */
    protected $transBox;

    /**
     * @param LoopInterface $loop
     * @param mixed[] $config
     */
    public function __construct(LoopInterface $loop, $config = [])
    {
        $this->loop = $loop;
        $this->config = $this->createConfig($config);
        $this->serverInfo = [];
        $this->state = self::STATE_INIT;
        $this->queue = $this->createQueue();
        $this->parser = null;
        $this->stream = null;
        $this->transBox = $this->createTransactionBox();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isPaused()
    {
        // TODO
        return false;
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
        return $this->state >= self::STATE_CONNECT_PENDING;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function start()
    {
        if ($this->isStarted())
        {
            return Promise::doResolve($this);
        }

        $promise = new Promise();

        $this->state = self::STATE_CONNECT_PENDING;
        $config = $this->config;

        $errorHandler = function ($command, $reason) use ($promise) {
            $this->state = self::STATE_AUTH_FAILED;
            return $promise->reject($reason);
        };

        $connectedHandler = function ($command, $info) use ($promise) {
            $this->state = self::STATE_AUTH_SUCCEEDED;
            $this->serverInfo = $info;
            return $promise->resolve($info);
        };

        $this
            ->connect()
            ->then(function($stream) use ($config, $errorHandler, $connectedHandler) {
                $this->stream = $stream;

                $stream->on('error', [ $this, 'handleSocketError' ]);
                $stream->on('close', [ $this, 'handleSocketClose' ]);

                $this->state  = self::STATE_AUTH_PENDING;
                $this->parser = new ProtocolParser($stream, $this->queue, $config);

                $command = $this->doAuth(new AuthCommand($this));
                $command->on('success', $connectedHandler);
                $command->on('error', $errorHandler);

                $this->parser->start();
            })
            ->done(null, [ $this, 'handleError' ]);

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function stop()
    {
        if (!$this->isStarted())
        {
            return Promise::doResolve($this);
        }
        return new Promise(function($resolve, $reject) {
            $this
                ->doCommand(new QuitCommand($this))
                ->on('success', function() use($resolve) {
                    $this->state = self::STATE_DISCONNECT_SUCCEEDED;
                    $this->emit('stop', [ $this ]);
                    $resolve($this);
                });
            $this->state = self::STATE_DISCONNECT_PENDING;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getInfo()
    {
        return $this->serverInfo;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function setDatabase($dbname)
    {
        return $this->query(sprintf('USE `%s`', $dbname));
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getDatabase()
    {
        // TODO
    }

    /**
     * @override
     * @inheritDoc
     */
    public function query($sql, $sqlParams = [])
    {
        $promise = new Promise();
        $query   = new Query($sql, $sqlParams);
        $command = new QueryCommand($this, $query);

        $this->doCommand($command);

        $command->on('error', function ($command, $err) use ($promise) {
            return $promise->reject($err);
        });
        $command->on('success', function ($command) use ($promise) {
            return $promise->resolve($command);
        });

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function execute($sql, $sqlParams = [])
    {
        return $this->query($sql, $sqlParams)->then(function($command) {
            return $command->affectedRows;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function ping()
    {
        $promise = new Promise();

        $command = $this->doCommand(new PingCommand($this));
        $command->on('error', function ($command, $reason) use ($promise) {
            return $promise->reject($reason);
        });
        $command->on('success', function () use ($promise) {
            return $promise->resolve();
        });

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function beginTransaction()
    {
        $trans = new Transaction($this);

        $trans->on('commit', function(TransactionInterface $trans, SplQueue $queue) {
            $this->commitTransaction($queue)->then(
                function() use($trans) {
                    return $trans->emit('success', [ $trans ]);
                },
                function($ex) use($trans) {
                    return $trans->emit('error', [ $trans, $ex ]);
                }
            );
            $this->transBox->remove($trans);
        });
        $trans->on('rollback', function(TransactionInterface $trans) {
            $this->transBox->remove($trans);
        });

        return $this->transBox->add($trans);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function endTransaction(TransactionInterface $trans)
    {
        return $trans->rollback();
    }

    /**
     * Try to commit a transaction.
     *
     * @param SplQueue $queue
     * @return PromiseInterface
     */
    protected function commitTransaction(SplQueue $queue)
    {
        $promise = new Promise();
        $ex = null;

        $queue->unshift(new QueryCommand($this, new Query('BEGIN')));
        $queue->unshift(new QueryCommand($this, new Query('START TRANSACTION')));

        $size = 0;
        $sizeCap = $queue->count();

        while (!$queue->isEmpty())
        {
            $command = $this->doCommand($queue->dequeue());
            $command->on('error', function($command, $err) use(&$ex, $promise) {
                if ($ex === null)
                {
                    $ex = $err;
                    $this->doCommand(new QueryCommand($this, new Query('ROLLBACK')));
                    $promise->reject($ex);
                }
            });
            $command->on('success', function() use (&$size, &$sizeCap, $promise) {
                if (++$size >= $sizeCap)
                {
                    $commit = $this->doCommand(new QueryCommand($this, new Query('COMMIT')));
                    $commit->on('success', function() use($promise) {
                        return $promise->resolve();
                    });
                    $commit->on('error', function($command, $err) use($promise) {
                        return $promise->reject($err);
                    });
                }
            });
        }

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function inTransaction()
    {
        return !$this->transBox->isEmpty();
    }

    /**
     * @internal
     */
    public function handleError($err)
    {
        $this->emit('error', [ $this, $err ]);
    }

    /**
     * @internal
     */
    public function handleSocketError($socket, $err)
    {
        $this->emit('error', [ $this, $err ]);
    }

    /**
     * @internal
     */
    public function handleSocketClose()
    {
        if ($this->state < self::STATE_DISCONNECT_PENDING)
        {
            $this->state = self::STATE_DISCONNECT_SUCCEEDED;
            $this->emit('error', [ $this, new RuntimeException('MySQL server has gone away!') ]);
        }
    }

    /**
     * Do auth command.
     *
     * @param CommandInterface $command
     * @return CommandInterface
     * @throws ExecutionException
     */
    protected function doAuth(CommandInterface $command)
    {
        if ($command->equals(Command::INIT_AUTHENTICATE))
        {
            return $this->queue->unshift($command);
        }
        throw new ExecutionException("Cann't send command");
    }

    /**
     * Do command.
     *
     * @param CommandInterface $command
     * @return CommandInterface
     * @throws ExecutionException
     */
    protected function doCommand($command)
    {
        if ($this->state >= self::STATE_CONNECT_PENDING && $this->state <= self::STATE_AUTH_SUCCEEDED)
        {
            return $this->queue->enqueue($command);
        }
        throw new ExecutionException("Cann't send command");
    }

    /**
     * Connect to the database endpoint.
     *
     * @return PromiseInterface
     */
    protected function connect()
    {
        return Promise::doResolve(
            new Socket($this->config['endpoint'], $this->getLoop())
        );
    }

    /**
     * Create Queue.
     *
     * @return Queue|QueueInterface
     */
    protected function createQueue()
    {
        return new Queue();
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
