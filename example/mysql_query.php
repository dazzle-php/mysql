<?php

/**
 * ---------------------------------------------------------------------------------------------------------------------
 * DESCRIPTION
 * ---------------------------------------------------------------------------------------------------------------------
 * This file contains the example of executing a query with MySQL.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 * USAGE
 * ---------------------------------------------------------------------------------------------------------------------
 * To run this example in CLI from project root use following syntax
 *
 * $> php ./example/mysql_query.php
 *
 * Following flags are supported to test example with different configurations:
 *
 * --endpoint : database connection endpoint, default: 'tcp://127.0.0.1:3306'
 * --user     : database user, default: 'root'
 * --pass     : database user password, default: 'root'
 * --dbname   : database name, default: 'dazzle'
 *
 * Ex:
 * $> php ./example/mysql_query.php --endpoint=tcp://88.20.52.120:8800 --user=abc --pass=abc123 --dbname=somedb
 *
 * ---------------------------------------------------------------------------------------------------------------------
 */

$endpoint = $user = $pass = $dbname = '';

require_once __DIR__ . '/bootstrap/autoload.php';

use Dazzle\Loop\Model\SelectLoop;
use Dazzle\Loop\Loop;
use Dazzle\MySQL\Database;

$loop = new Loop(new SelectLoop);

$mysql = new Database($loop, [
    'endpoint' => $endpoint,
    'user'     => $user,
    'pass'     => $pass,
    'dbname'   => $dbname,
]);

$mysql->start()
    ->then(function() use($mysql) {
        printf("Connection has been established!\n");
        printf("Connection state is %s\n", $mysql->getState());
    })
    ->done(null, function($ex) {
        printf("Error: %s\n", var_export((string) $ex, true));
    });

$mysql->query('SHOW TABLES')
    ->then(function ($command) use ($loop) {
        $results = $command->resultRows;
        $fields  = $command->resultFields;

        printf("|%-60s|\n", str_repeat('-', 60));
        printf("|%-60s|\n", ' ' . $fields[0]['name']);
        printf("|%-60s|\n", str_repeat('-', 60));

        foreach ($results as $result)
        {
            printf("| # %-56s |\n", $result[$fields[0]['name']]);
        }
        printf("|%-60s|\n", str_repeat('-', 60));
    })
    ->then(null, function($ex) {
        printf("Error: %s\n", var_export((string) $ex, true));
    })
    ->done(function() use($loop) {
        $loop->stop();
    });

$loop->start();
