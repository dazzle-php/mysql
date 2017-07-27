<?php

namespace Dazzle\MySQL;

use Dazzle\Promise\PromiseInterface;

interface SQLClientInterface
{
    /**
     * Execute an async query.
     *
     * @param string $sql
     * @param mixed[] $sqlParams
     * @return PromiseInterface
     */
    public function query($sql, $sqlParams = []);

    /**
     * Execute an async query and return number of affected rows.
     *
     * @param string $sql
     * @param mixed[] $sqlParams
     * @return PromiseInterface
     */
    public function execute($sql, $sqlParams = []);
}

