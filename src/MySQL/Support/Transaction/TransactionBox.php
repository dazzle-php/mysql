<?php

namespace Dazzle\MySQL\Support\Transaction;

use Dazzle\MySQL\TransactionInterface;
use SplQueue;

class TransactionBox implements TransactionBoxInterface
{
    /**
     * @var SplQueue
     */
    protected $queue;

    /**
     *
     */
    public function __construct()
    {
        $this->queue = new SplQueue;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isEmpty()
    {
        return $this->queue->isEmpty();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function enqueue(TransactionInterface $trans)
    {
        $this->queue->enqueue($trans);
        return $trans;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function dequeue()
    {
        return $this->queue->dequeue();
    }
}
