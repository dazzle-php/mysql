# Dazzle Async MySQL Driver

[![Build Status](https://travis-ci.org/dazzle-php/mysql.svg)](https://travis-ci.org/dazzle-php/mysql)
[![Code Coverage](https://scrutinizer-ci.com/g/dazzle-php/mysql/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dazzle-php/mysql/?branch=master)
[![Code Quality](https://scrutinizer-ci.com/g/dazzle-php/mysql/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dazzle-php/mysql/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/dazzle-php/mysql/v/stable)](https://packagist.org/packages/dazzle-php/mysql) 
[![Latest Unstable Version](https://poser.pugx.org/dazzle-php/mysql/v/unstable)](https://packagist.org/packages/dazzle-php/mysql) 
[![License](https://poser.pugx.org/dazzle-php/mysql/license)](https://packagist.org/packages/dazzle-php/mysql/license)

> **Note:** This repository is part of [Dazzle Project](https://github.com/dazzle-php/dazzle) - the next-gen library for PHP. The project's purpose is to provide PHP developers with a set of complete tools to build functional async applications. Please, make sure you read the attached README carefully and it is guaranteed you will be surprised how easy to use and powerful it is. In the meantime, you might want to check out the rest of our async libraries in [Dazzle repository](https://github.com/dazzle-php) for the full extent of Dazzle experience.

<br>
<p align="center">
<img src="https://raw.githubusercontent.com/dazzle-php/dazzle/master/media/dazzle-x125.png" />
</p>

## Description

TODO

## Feature Highlights

Dazzle MySQL features:

TODO

## Provided Example(s)

### Quickstart

This example demonstrates how to connect to MySQL database and print all tables stored inside it. 

```php
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
```

### Additional

TODO

## Comparison

This section contains Dazzle vs React comparison many users requested. If you are wondering why this section has been created, see the [author's note](https://github.com/dazzle-php/mysql/blob/master/NOTE.md) at the end of it.

#### Performance

TODO

#### Details

TODO

#### Note from the author

Note is available in [NOTE file](https://github.com/dazzle-php/mysql/blob/master/NOTE.md).

## Requirements

Dazzle MySQL requires:

* PHP-5.6 or PHP-7.0+,
* UNIX or Windows OS.

## Installation

To install this library make sure you have [composer](https://getcomposer.org/) installed, then run following command:

```
$> composer require dazzle-php/mysql
```

## Tests

Tests can be run via:

```
$> vendor/bin/phpunit -d memory_limit=1024M
```

## Versioning

Versioning of Dazzle libraries is being shared between all packages included in [Dazzle Project](https://github.com/dazzle-php/dazzle). That means the releases are being made concurrently for all of them. On one hand this might lead to "empty" releases for some packages at times, but don't worry. In the end it is far much easier for contributors to maintain and -- what's the most important -- much more straight-forward for users to understand the compatibility and inter-operability of the packages.

## Contributing

Thank you for considering contributing to this repository! 

- The contribution guide can be found in the [contribution tips](https://github.com/dazzle-php/mysql/blob/master/CONTRIBUTING.md). 
- Open tickets can be found in [issues section](https://github.com/dazzle-php/mysql/issues). 
- Current contributors are listed in [graphs section](https://github.com/dazzle-php/mysql/graphs/contributors)
- To contact the author(s) see the information attached in [composer.json](https://github.com/dazzle-php/mysql/blob/master/composer.json) file.

## License

Dazzle MySQL is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).

<hr>
<p align="center">
<i>"Everything is possible. The impossible just takes longer."</i> ― Dan Brown
</p>
