<?php

namespace Dazzle\MySQL\Driver\Command;

use Dazzle\MySQL\Driver\Command;
use Dazzle\MySQL\Query;

class QueryCommand extends Command
{
    public $query;
    public $fields;
    public $insertId;
    public $affectedRows;

    public function getID()
    {
        return self::QUERY;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setQuery($query)
    {
        if (! $query instanceof Query)
        {
            $query = new Query($query);
        }
        $this->query = $query;
    }

    public function getSql()
    {
        $query = $this->query;

        if ($query instanceof Query)
        {
            return $query->getSql();
        }
        return $query;
    }

    public function buildPacket()
    {}
}
