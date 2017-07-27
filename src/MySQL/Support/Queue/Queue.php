<?php

namespace Dazzle\MySQL\Support\Queue;

use Dazzle\Event\BaseEventEmitter;
use Dazzle\MySQL\Protocol\CommandInterface;
use SplQueue;

class Queue extends BaseEventEmitter implements QueueInterface
{
    /**
     * @var SplQueue
     */
    public $queue;

    /**
     *
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isEmpty()
    {
        return $this->queue->isEmpty();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function enqueue(CommandInterface $command)
    {
        $this->queue->enqueue($command);
        $this->emit('new');
        return $command;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function dequeue()
    {
        return $this->queue->dequeue();
    }

    /**
     * Unshift command.
     *
     * @return CommandInterface
     */
    public function unshift(CommandInterface $command)
    {
        $this->queue->unshift($command);
        return $command;
    }
}
