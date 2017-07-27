<?php

namespace Dazzle\MySQL;

use Dazzle\Event\BaseEventEmitter;
use Dazzle\MySQL\Protocol\Command\QueryCommand;
use Dazzle\MySQL\Protocol\CommandInterface;
use Dazzle\Promise\Promise;
use Dazzle\Throwable\Exception\Runtime\ExecutionException;

class Transaction extends BaseEventEmitter implements TransactionInterface
{
    /**
     * @var DatabaseInterface
     */
    protected $database;

    /**
     * @var CommandInterface[]
     */
    protected $commands;

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
        $this->commands = [];
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
        $query   = new Query($sql);
        $command = new QueryCommand($this->database);

        $command->setQuery($query);
        $query->bindParamsFromArray($sqlParams);

        $command->on('results', function ($rows, $command) use ($promise) {
            return $command->hasError()
                ? $promise->reject($command->getError())
                : $promise->resolve($command);
        });
        $command->on('error', function ($err, $command) use ($promise) {
            return $promise->reject($err);
        });
        $command->on('success', function ($command) use ($promise) {
            return $command->hasError()
                ? $promise->reject($command->getError())
                : $promise->resolve($command);
        });

        $this->commands[] = $command;

        return $promise;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function execute($sql, $sqlParams = [])
    {
        if (!$this->isOpen())
        {
            return Promise::doReject(new ExecutionException('This transaction is no longer open.'));
        }

        // TODO: Implement execute() method.
    }

    /**
     * @override
     * @inheritDoc
     */
    public function commit()
    {
        $this->open = false;
        $this->emit('commit');
    }

    /**
     * @override
     * @inheritDoc
     */
    public function rollback()
    {
        $this->open = false;
        $this->emit('rollback');
    }
}
