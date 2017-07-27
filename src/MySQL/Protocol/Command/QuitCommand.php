<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;

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
