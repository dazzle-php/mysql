<?php

namespace Dazzle\MySQL\Command;

use Dazzle\Event\BaseEventEmitter;
use Dazzle\MySQL\DatabaseInterface;

abstract class Command extends BaseEventEmitter implements CommandInterface
{
    /**
     * Code of mysql internat thread state.
     *
     * @var int
     */
    const SLEEP = 0x00;

    /**
     * Code of mysql_close.
     *
     * @var int
     */
    const QUIT = 0x01;

    /**
     * Code of mysql_select_db.
     *
     * @var int
     */
    const INIT_DB = 0x02;

    /**
     * Code of mysql_real_query.
     *
     * @var int
     */
    const QUERY = 0x03;

    /**
     * Code of mysql_list_fields.
     *
     * @var int
     */
    const FIELD_LIST = 0x04;

    /**
     * Code of mysql_create_db (deprecated).
     *
     * @var int
     */
    const CREATE_DB = 0x05;

    /**
     * Code of mysql_drop_db (deprecated).
     *
     * @var int
     */
    const DROP_DB = 0x06;

    /**
     * Code of mysql_refresh.
     *
     * @var int
     */
    const REFRESH = 0x07;

    /**
     * Code of mysql_shutdown.
     *
     * @var int
     */
    const SHUTDOWN = 0x08;

    /**
     * Code of mysql_stat.
     *
     * @var int
     */
    const STATISTICS = 0x09;

    /**
     * Code of mysql_list_processes.
     *
     * @var int
     */
    const PROCESS_INFO = 0x0a;

    /**
     * Code representing internal thread state.
     *
     * @var int
     */
    const CONNECT = 0x0b;

    /**
     * Code of mysql_kill.
     *
     * @var int
     */
    const PROCESS_KILL = 0x0c;

    /**
     * Code of mysql_dump_debug_info.
     *
     * @var int
     */
    const DEBUG = 0x0d;

    /**
     * Code of mysql_ping.
     *
     * @var int
     */
    const PING = 0x0e;

    /**
     * Code representing internal thread state.
     *
     * @var int
     */
    const TIME = 0x0f;

    /**
     * Code representing internal thread state.
     *
     * @var int
     */
    const DELAYED_INSERT = 0x10;

    /**
     * Code of mysql_change_user.
     *
     * @var int
     */
    const CHANGE_USER = 0x11;

    /**
     * Code sent by the slave IO thread to request a binlog
     *
     * @var int
     */
    const BINLOG_DUMP = 0x12;

    /**
     * Code of "LOAD TABLE ... FROM MASTER" (deprecated).
     *
     * @var int
     */
    const TABLE_DUMP = 0x13;

    /**
     * Code representing internal thread state
     *
     * @var int
     */
    const CONNECT_OUT = 0x14;

    /**
     * Code sent by the slave to register with the master (optional).
     *
     * @var int
     */
    const REGISTER_SLAVE = 0x15;

    /**
     * Code of mysql_stmt_prepare.
     *
     * @var int
     */
    const STMT_PREPARE = 0x16;

    /**
     * Code of mysql_stmt_execute
     *
     * @var int
     */
    const STMT_EXECUTE = 0x17;

    /**
     * Code of mysql_stmt_send_long_data
     *
     * @var int
     */
    const STMT_SEND_LONG_DATA = 0x18;

    /**
     * Code of mysql_stmt_close
     *
     * @var int
     */
    const STMT_CLOSE = 0x19;

    /**
     * Code of mysql_stmt_reset
     *
     * @var int
     */
    const STMT_RESET = 0x1a;

    /**
     * Code of mysql_set_server_option
     *
     * @var int
     */
    const SET_OPTION = 0x1b;

    /**
     * Code of mysql_stmt_fetch
     *
     * @var int
     */
    const STMT_FETCH = 0x1c;

    /**
     * Authenticate after the connection is established.
     *
     * @var int
     */
    const INIT_AUTHENTICATE = 0xf1;

    protected $database;

    private $states = [];

    private $error;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function getState($name, $default = null)
    {
        if (isset($this->states[$name]))
        {
            return $this->states[$name];
        }
        return $default;
    }

    public function setState($name, $value)
    {
        $this->states[$name] = $value;

        return $this;
    }

    public function equals($commandId)
    {
        return $this->getID() === $commandId;
    }

    public function setError(\Exception $error)
    {
        $this->error = $error;
    }

    public function getError()
    {
        return $this->error;
    }

    public function hasError()
    {
        return (boolean) $this->error;
    }

    public function getConnection()
    {
        return $this->database;
    }
}
