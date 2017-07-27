<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;
use Dazzle\MySQL\Protocol\QueryInterface;
use Dazzle\MySQL\SQLResultInterface;

class QueryCommand extends Command implements SQLResultInterface
{
    public $query;
    public $fields;
    public $insertId;
    public $affectedRows;

    /**
     * @var int
     */
    protected $id = self::QUERY;

    /**
     * @override
     * @inheritDoc
     */
    public function getSQL()
    {
        return ($this->context instanceof QueryInterface) ? $this->context->getSQL() : $this->context;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getRows()
    {
        return $this->resultRows;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getFields()
    {
        return $this->resultFields;
    }
}
