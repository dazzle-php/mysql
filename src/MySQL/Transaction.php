<?php

namespace Dazzle\MySQL;

use Dazzle\Event\BaseEventEmitter;
use Dazzle\MySQL\Protocol\Command\QueryCommand;
use Dazzle\MySQL\Protocol\CommandInterface;
use Dazzle\MySQL\Protocol\Query;
use Dazzle\MySQL\Protocol\QueryInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;
use Dazzle\Throwable\Exception\Runtime\ExecutionException;
use SplQueue;

class Transaction extends BaseEventEmitter implements TransactionInterface
{
    /**
     * @var DatabaseInterface
     */
    protected $database;

    /**
     * @var CommandInterface[]
     */
    protected $queue;

    /**
     * @var bool
     */
    protected $open;

    /**
     * @param DatabaseInterface $database
     */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
        $this->queue = [];
        $this->open = true;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isOpen()
    {
        return $this->open;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function query($sql, $sqlParams = [])
    {
        if (!$this->isOpen())
        {
            return Promise::doReject(new ExecutionException('This transaction is no longer open.'));
        }

        $promise = new Promise();
        $query   = new Query($sql, $sqlParams);
        $command = new QueryCommand($this->database, $query);

        $this->on('error', function ($trans, $err) use ($promise) {
            return $promise->reject($err);
        });
        $this->on('success', function ($trans) use ($promise, $command) {
            return $promise->resolve($command);
        });

        $this->queue[] = $command;

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function execute($sql, $sqlParams = [])
    {
        // TODO: Implement execute() method.
    }

    /**
     * @override
     * @inheritDoc
     */
    public function commit()
    {
        if (!$this->isOpen())
        {
            return Promise::doReject(new ExecutionException('This transaction is no longer open.'));
        }

        $promise = new Promise();

        $this->on('error', function ($trans, $err) use ($promise) {
            return $promise->reject($err);
        });
        $this->on('success', function ($trans) use ($promise) {
            return $promise->resolve();
        });

        $this->open = false;
        $this->emit('commit', [ $this, $this->queue ]);
        $this->queue = [];

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function rollback()
    {
        if (!$this->isOpen())
        {
            return Promise::doReject(new ExecutionException('This transaction is no longer open.'));
        }

        $this->open = false;
        $this->emit('rollback', [ $this ]);
        $this->queue = [];

        return Promise::doResolve();
    }
}
