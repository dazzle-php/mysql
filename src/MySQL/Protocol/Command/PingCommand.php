<?php

namespace Dazzle\MySQL\Protocol\Command;

use Dazzle\MySQL\Protocol\Command;

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
