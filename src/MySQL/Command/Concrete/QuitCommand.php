<?php

namespace Dazzle\MySQL\Command\Concrete;

use Dazzle\MySQL\Command\Command;

class QuitCommand extends Command
{
    public function getID()
    {
        return self::QUIT;
    }

    public function buildPacket()
    {}

    public function getSql()
    {
        return '';
    }
}
