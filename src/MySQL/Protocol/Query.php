<?php

namespace Dazzle\MySQL\Protocol;

use Dazzle\Throwable\Exception\LogicException;

class Query implements QueryInterface
{
    /**
     * @var string
     */
    protected $sql;

    /**
     * @var string
     */
    protected $sqlPrepared;

    /**
     * @var mixed[]
     */
    protected $params = [];

    /**
     * @var string[]
     */
    private $escapeChars = [
        "\x00" => "\\0",
        "\r"   => "\\r",
        "\n"   => "\\n",
        "\t"   => "\\t",
        "'"    => "\'",
        '"'    => '\"',
        "\\"   => "\\\\",
    ];

    /**
     * @param string $sql
     * @param mixed[] $sqlParams
     */
    public function __construct($sql, $sqlParams = [])
    {
        $this->sql = $sql;
        $this->sqlPrepared = null;
        $this->params = $sqlParams;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function bindParams(...$args)
    {
        $this->sqlPrepared = null;
        $this->params = $args;

        return $this;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function bindParamsFromArray(array $params = [])
    {
        $this->sqlPrepared = null;
        $this->params = $params;

        return $this;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getSQL()
    {
        return $this->sqlPrepared === null ? $this->buildSQL() : $this->sqlPrepared;
    }

    /**
     * Escape string.
     *
     * @param string $str
     * @return string
     */
    protected function escape($str)
    {
        return strtr($str, $this->escapeChars);
    }

    /**
     * Resolve value for SQL.
     *
     * @param  mixed $value
     * @return string
     */
    protected function resolveValueForSql($value)
    {
        $type = gettype($value);

        switch ($type)
        {
            case 'boolean':
                $value = (int) $value;
                break;
            case 'double':
            case 'integer':
                break;
            case 'string':
                $value = "'" . $this->escape($value) . "'";
                break;
            case 'array':
                $nvalue = [];
                foreach ($value as $v) {
                    $nvalue[] = $this->resolveValueForSql($v);
                }
                $value = implode(',', $nvalue);
                break;
            case 'null':
                $value = 'null';
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Not supportted value type of %s.', $type));
                break;
        }

        return $value;
    }

    /**
     * Build sql query replacing and escaping characters.
     *
     * @return string
     * @throws LogicException
     */
    protected function buildSQL()
    {
        $sql = $this->sql;
        $keys = array_map(function($key) { return is_numeric($key) ? '\?' : $key; }, array_keys($this->params));
        $params = array_values($this->params);
        $size = count($params);

        for ($i=0; $i<$size; $i++)
        {
            $sql = preg_replace('#' . $keys[$i] . '#', $this->resolveValueForSql($params[$i]), $sql, 1);
        }

        return $sql;
    }
}
