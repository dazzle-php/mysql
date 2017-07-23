<?php

namespace Dazzle\MySQL\Command\Concrete;

use Dazzle\MySQL\Command\Command;

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
