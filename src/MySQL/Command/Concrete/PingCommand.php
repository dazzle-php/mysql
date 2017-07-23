<?php

namespace Dazzle\MySQL\Command\Concrete;

use Dazzle\MySQL\Command\Command;

class PingCommand extends Command
{
    /**
     * @override
     * @inheritDoc
     */
    public function getID()
    {
        return self::PING;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function buildPacket()
    {}

    /**
     * @override
     * @inheritDoc
     */
    public function getSql()
    {
        return '';
    }
}
