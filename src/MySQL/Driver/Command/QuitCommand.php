<?php

namespace Dazzle\MySQL\Driver\Command;

use Dazzle\MySQL\Driver\Command;

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
