<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;

class PingCommand extends Command
{
    /**
     * @var int
     */
    protected $id = self::PING;
}
