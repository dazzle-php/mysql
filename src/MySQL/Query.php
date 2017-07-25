<?php

namespace Dazzle\MySQL;

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
    protected $escapeChars = [
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
     */
    public function __construct($sql)
    {
        $this->sql = $sql;
        $this->sqlPrepared = '';
    }

    /**
     * Binding params for the query, mutiple arguments support.
     *
     * @param  mixed              $param
     * @return Query
     */
    public function bindParams()
    {
        $this->sqlPrepared = null;
        $this->params   = func_get_args();

        return $this;
    }

    public function bindParamsFromArray(array $params)
    {
        $this->sqlPrepared = null;
        $this->params   = $params;

        return $this;
    }

    public function escape($str)
    {
        return strtr($str, $this->escapeChars);
    }

    /**
     * @param  mixed  $value
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

    protected function buildSql()
    {
        $sql = $this->sql;
        $offset = strpos($sql, '?');

        foreach ($this->params as $param)
        {
            $replacement = $this->resolveValueForSql($param);
            $sql = substr_replace($sql, $replacement, $offset, 1);
            $offset = strpos($sql, '?', $offset + strlen($replacement));
        }

        if ($offset !== false)
        {
            throw new \LogicException('Params not enough to build sql');
        }

        return $sql;
    }

    /**
     * Get the constructed and escaped sql string.
     *
     * @return string
     */
    public function getSql()
    {
        if ($this->sqlPrepared === null)
        {
            $this->sqlPrepared = $this->buildSql();
        }

        return $this->sqlPrepared;
    }
}
