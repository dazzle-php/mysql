<?php

namespace Dazzle\MySQL;

interface SQLQueryInterface
{
    /**
     * Get returned rows.
     *
     * @return mixed
     */
    public function getRows();

    /**
     * Get returned fields.
     *
     * @return mixed
     */
    public function getFields();
}
