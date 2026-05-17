<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\MemoryService;
use Zephyrisle\ZaiBot\Service\SettingAccessor;
use Zephyrisle\ZaiBot\Service\StatsService;

class MaintenanceController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(
        private MemoryService $memory,
        private StatsService $stats,
        private SettingAccessor $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        $body = $this->body($request);
        $action = (string) Arr::get($body, 'action', 'export');

        return match ($action) {
            'decayMemories' => $this->ok(['data' => $this->memory->decayAll()]),
            'cleanupSessions' => $this->ok(['deleted' => $this->memory->cleanupSessionState()]),
            'updatePersonalityTags' => $this->ok(['updated' => $this->memory->updatePersonalityTags()]),
            'dashboard' => $this->ok(['stats' => $this->stats->summary()]),
            default => $this->ok([
                'settings' => $this->settings->allDefaultsMerged(),
                'stats' => $this->stats->summary(),
            ]),
        };
    }
}
