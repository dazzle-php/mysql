<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;

class AuthCommand extends Command
{
    /**
     * @override
     * @inheritDoc
     */
    public function getID()
    {
        return self::INIT_AUTHENTICATE;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function buildPacket()
    {}
}
