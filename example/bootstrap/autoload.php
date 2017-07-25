<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$options = [
    'endpoint' => 'tcp://127.0.0.1:3306',
    'user'     => 'root',
    'pass'     => 'root',
    'dbname'   => 'dazzle',
];

foreach ($argv as $argIndex=>$argVal)
{
    if ($argIndex && preg_match('#^--([^=]+)=(.+)$#si', $argVal, $matches) && $matches)
    {
        $options[$matches[1]] = $matches[2];
    }
}

foreach ($options as $optionKey=>$optionVal)
{
    $$optionKey = $optionVal;
}
