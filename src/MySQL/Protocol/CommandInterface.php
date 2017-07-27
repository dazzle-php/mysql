<?php

namespace Dazzle\MySQL\Protocol;

use Dazzle\Event\EventEmitterInterface;

interface CommandInterface extends EventEmitterInterface
{
    public function buildPacket();
    public function getID();
    public function setState($name, $value);
    public function getState($name, $default = null);
    public function equals($commandId);
}
