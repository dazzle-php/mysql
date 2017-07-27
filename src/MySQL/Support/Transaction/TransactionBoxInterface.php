<?php

namespace Dazzle\MySQL\Support\Transaction;

use Dazzle\MySQL\TransactionInterface;

interface TransactionBoxInterface
{
    public function isEmpty();

    public function add(TransactionInterface $trans);

    public function remove(TransactionInterface $trans);
}
