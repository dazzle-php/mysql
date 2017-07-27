<?php

namespace Dazzle\MySQL\Support\Transaction;

use Dazzle\MySQL\TransactionInterface;
use SplObjectStorage;

class TransactionBox implements TransactionBoxInterface
{
    /**
     * @var SplObjectStorage
     */
    protected $collection;

    /**
     *
     */
    public function __construct()
    {
        $this->collection = new SplObjectStorage();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isEmpty()
    {
        return $this->collection->count() <= 0;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function add(TransactionInterface $trans)
    {
        $this->collection->attach($trans);
        return $trans;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function remove(TransactionInterface $trans)
    {
        $this->collection->detach($trans);
        return $trans;
    }
}
