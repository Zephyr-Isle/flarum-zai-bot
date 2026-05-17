<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\SettingAccessor;
use Zephyrisle\ZaiBot\Service\StatsService;

class DashboardController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(
        private StatsService $stats,
        private SettingAccessor $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        return $this->ok([
            'stats' => $this->stats->summary(),
            'settings' => $this->settings->allDefaultsMerged(),
        ]);
    }
}
