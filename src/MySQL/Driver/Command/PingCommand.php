<?php

namespace Dazzle\MySQL\Driver\Command;

use Dazzle\MySQL\Driver\Command;

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
