<?php

namespace Zephyrisle\ZaiBot\Service;

use Zephyrisle\ZaiBot\Model\AiActionLog;
use Zephyrisle\ZaiBot\Model\AiAgent;
use Zephyrisle\ZaiBot\Model\AiProvider;
use Zephyrisle\ZaiBot\Model\ConversationMemory;

class StatsService
{
    public function summary(): array
    {
        return [
            'providerCount' => AiProvider::query()->count(),
            'agentCount' => AiAgent::query()->count(),
            'activeAgentCount' => AiAgent::query()->where('is_active', true)->count(),
            'memoryCount' => ConversationMemory::query()->count(),
            'actionCount' => AiActionLog::query()->count(),
            'successfulActionCount' => AiActionLog::query()->where('result', 'success')->count(),
            'failedActionCount' => AiActionLog::query()->whereIn('result', ['failed', 'denied'])->count(),
        ];
    }
}