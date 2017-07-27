<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;

class QuitCommand extends Command
{
    /**
     * @var int
     */
    protected $id = self::QUIT;
}
