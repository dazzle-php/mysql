<?php

namespace Dazzle\MySQL;

use Dazzle\Event\EventEmitterInterface;
use Dazzle\Loop\LoopResourceInterface;
use Dazzle\Promise\PromiseInterface;

interface DatabaseInterface extends LoopResourceInterface, EventEmitterInterface
{
    /**
     * Start the MySQL connection.
     *
     * @return PromiseInterface
     */
    public function start();

    /**
     * Stop the MySQL connection.
     *
     * @return PromiseInterface
     */
    public function stop();
}
