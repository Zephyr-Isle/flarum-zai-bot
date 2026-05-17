<?php

namespace Zephyrisle\ZaiBot\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Zephyrisle\ZaiBot\Service\AiResponder;

class ProcessAiResponseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $postId)
    {
    }

    public function handle(AiResponder $responder): void
    {
        $responder->respondToPost($this->postId);
    }
}
