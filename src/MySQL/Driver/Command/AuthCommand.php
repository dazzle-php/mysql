<?php

namespace Dazzle\MySQL\Driver\Command;

use Dazzle\MySQL\Driver\Command;

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
