<?php

namespace Dazzle\MySQL\Protocol;

class Protocol
{
    /**
     * new more secure passwords.
     *
     * @var int
     */
    const CLIENT_LONG_PASSWORD = 1;

    /**
     * Found instead of affected rows.
     *
     * @var int
     */
    const CLIENT_FOUND_ROWS = 2;

    /**
     * Get all column flags.
     *
     * @var int
     */
    const CLIENT_LONG_FLAG = 4;

    /**
     * One can specify db on connect.
     *
     * @var int
     */
    const CLIENT_CONNECT_WITH_DB = 8;

    /**
     * Don't allow database.table.column.
     *
     * @var int
     */
    const CLIENT_NO_SCHEMA = 16;

    /**
     * Can use compression protocol.
     *
     * @var int
     */
    const CLIENT_COMPRESS = 32;

    /**
     * Odbc client.
     *
     * @var int
     */
    const CLIENT_ODBC = 64;

    /**
     * Can use LOAD DATA LOCAL.
     *
     * @var int
     */
    const CLIENT_LOCAL_FILES = 128;

    /**
     * Ignore spaces before '('.
     *
     * @var int
     */
    const CLIENT_IGNORE_SPACE = 256;

    /**
     * New 4.1 protocol.
     *
     * @var int
     */
    const CLIENT_PROTOCOL_41 = 512;

    /**
     * This is an interactive client.
     *
     * @var int
     */
    const CLIENT_INTERACTIVE = 1024;

    /**
     * Switch to SSL after handshake.
     *
     * @var int
     */
    const CLIENT_SSL = 2048;

    /**
     * IGNORE sigpipes.
     *
     * @var int
     */
    const CLIENT_IGNORE_SIGPIPE = 4096;

    /**
     * Client knows about transactions.
     *
     * @var int
     */
    const CLIENT_TRANSACTIONS = 8192;

    /**
     * Old flag for 4.1 protocol.
     *
     * @var int
     */
    const CLIENT_RESERVED = 16384;

    /**
     * New 4.1 authentication.
     *
     * @var int
     */
    const CLIENT_SECURE_CONNECTION = 32768;

    /**
     * Enable/disable multi-stmt support.
     *
     * @var int
     */
    const CLIENT_MULTI_STATEMENTS = 65536;

    /**
     * Enable/disable multi-results.
     *
     * @var int
     */
    const CLIENT_MULTI_RESULTS = 131072;

    const FIELD_TYPE_DECIMAL     = 0x00;
    const FIELD_TYPE_TINY        = 0x01;
    const FIELD_TYPE_SHORT       = 0x02;
    const FIELD_TYPE_LONG        = 0x03;
    const FIELD_TYPE_FLOAT       = 0x04;
    const FIELD_TYPE_DOUBLE      = 0x05;
    const FIELD_TYPE_null        = 0x06;
    const FIELD_TYPE_TIMESTAMP   = 0x07;
    const FIELD_TYPE_LONGLONG    = 0x08;
    const FIELD_TYPE_INT24       = 0x09;
    const FIELD_TYPE_DATE        = 0x0a;
    const FIELD_TYPE_TIME        = 0x0b;
    const FIELD_TYPE_DATETIME    = 0x0c;
    const FIELD_TYPE_YEAR        = 0x0d;
    const FIELD_TYPE_NEWDATE     = 0x0e;
    const FIELD_TYPE_VARCHAR     = 0x0f;
    const FIELD_TYPE_BIT         = 0x10;
    const FIELD_TYPE_NEWDECIMAL  = 0xf6;
    const FIELD_TYPE_ENUM        = 0xf7;
    const FIELD_TYPE_SET         = 0xf8;
    const FIELD_TYPE_TINY_BLOB   = 0xf9;
    const FIELD_TYPE_MEDIUM_BLOB = 0xfa;
    const FIELD_TYPE_LONG_BLOB   = 0xfb;
    const FIELD_TYPE_BLOB        = 0xfc;
    const FIELD_TYPE_VAR_STRING  = 0xfd;
    const FIELD_TYPE_STRING      = 0xfe;
    const FIELD_TYPE_GEOMETRY    = 0xff;

    const NOT_null_FLAG          = 0x1;
    const PRI_KEY_FLAG           = 0x2;
    const UNIQUE_KEY_FLAG        = 0x4;
    const MULTIPLE_KEY_FLAG      = 0x8;
    const BLOB_FLAG              = 0x10;
    const UNSIGNED_FLAG          = 0x20;
    const ZEROFILL_FLAG          = 0x40;
    const BINARY_FLAG            = 0x80;
    const ENUM_FLAG              = 0x100;
    const AUTO_INCREMENT_FLAG    = 0x200;
    const TIMESTAMP_FLAG         = 0x400;
    const SET_FLAG               = 0x800;
}
