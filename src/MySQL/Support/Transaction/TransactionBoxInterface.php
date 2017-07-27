<?php

namespace Dazzle\MySQL\Support\Transaction;

use Dazzle\MySQL\TransactionInterface;

interface TransactionBoxInterface
{
    public function isEmpty();

    public function enqueue(TransactionInterface $trans);

    public function dequeue();
}
