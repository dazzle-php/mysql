<?php

require __DIR__ . '/init.php';

use Dazzle\Loop\Model\SelectLoop;
use Dazzle\Loop\Loop;
use Dazzle\MySQL\Database;

$loop = new Loop(new SelectLoop);

$mysql = new Database($loop, [
    'endpoint' => 'tcp://127.0.0.1:3306',
    'user'     => 'root',
    'pass'     => 'root',
    'dbname'   => 'dazzle',
]);

$mysql
    ->start()
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
