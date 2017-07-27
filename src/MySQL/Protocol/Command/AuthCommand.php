<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;

class AuthCommand extends Command
{
    /**
     * @var int
     */
    protected $id = self::INIT_AUTHENTICATE;
}
