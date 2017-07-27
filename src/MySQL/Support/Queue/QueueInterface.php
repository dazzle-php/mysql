<?php

namespace Dazzle\MySQL\Support\Queue;

use Dazzle\Event\EventEmitterInterface;
use Dazzle\MySQL\Protocol\Command;
use Dazzle\MySQL\Protocol\CommandInterface;

interface QueueInterface extends EventEmitterInterface
{
    /**
     * Check if queue is empty.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Enqueue new command.
     *
     * @param CommandInterface $command
     * @return mixed
     */
    public function enqueue(CommandInterface $command);

    /**
     * Dequeue fist command.
     *
     * @return CommandInterface|null
     */
    public function dequeue();
}
