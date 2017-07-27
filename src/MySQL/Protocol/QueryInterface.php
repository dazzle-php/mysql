<?php

namespace Dazzle\MySQL\Protocol;

interface QueryInterface
{
    /**
     * Bind params for the query using spreaded array.
     *
     * @return QueryInterface
     */
    public function bindParams(...$args);

    /**
     * Bind params for the query using array of arguments.
     *
     * @param mixed[] $params
     * @return QueryInterface
     */
    public function bindParamsFromArray(array $params = []);

    /**
     * Get the constructed and escaped sql string.
     *
     * @return string
     */
    public function getSQL();
}
