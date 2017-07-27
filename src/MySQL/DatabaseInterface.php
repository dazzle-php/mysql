<?php

namespace Dazzle\MySQL;

use Dazzle\Event\EventEmitterInterface;
use Dazzle\Loop\LoopResourceInterface;
use Dazzle\Promise\PromiseInterface;

interface DatabaseInterface extends SQLInterface, LoopResourceInterface, EventEmitterInterface
{
    /**
     * Find out if the database has been started.
     *
     * @return bool
     */
    public function isStarted();

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

    /**
     * Get state of MySQL connection.
     *
     * @return int
     */
    public function getState();

    /**
     * Get server info.
     *
     * @return mixed[]
     */
    public function getInfo();

    /**
     * Set current database.
     *
     * @param string $dbname
     * @return PromiseInterface
     */
    public function setDatabase($dbname);

    /**
     * Get current database
     *
     * @return PromiseInterface
     */
    public function getDatabase();

    /**
     * Create and being an new transaction.
     *
     * @return TransactionInterface
     */
    public function beginTransaction();

    /**
     * End all (rollback) currently opened transactions.
     */
    public function endTransaction(TransactionInterface $trans);

    /**
     * Check whether database has any pending transactions.
     *
     * @return bool
     */
    public function inTransaction();
}
