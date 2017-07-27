<?php

namespace Dazzle\MySQL;

use Dazzle\Event\EventEmitterInterface;

interface TransactionInterface extends SQLInterface, EventEmitterInterface
{
    public function isOpen();

    public function commit();

    public function rollback();
}
