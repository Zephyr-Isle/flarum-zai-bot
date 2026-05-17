<?php

namespace Zephyrisle\ZaiBot\Listener;

use Flarum\Post\Event\Posted;
use Illuminate\Contracts\Bus\Dispatcher;
use Zephyrisle\ZaiBot\Job\ProcessAiResponseJob;

class QueueAiResponseOnPost
{
    public function __construct(private Dispatcher $bus)
    {
    }

    public function handle(Posted $event): void
    {
        if ($event->post->type !== 'comment') {
            return;
        }

        $this->bus->dispatch(new ProcessAiResponseJob((int) $event->post->id));
    }
}
