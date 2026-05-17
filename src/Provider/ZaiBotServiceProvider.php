<?php

namespace Zephyrisle\ZaiBot\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use GuzzleHttp\Client;
use Zephyrisle\ZaiBot\Service\AgentManager;
use Zephyrisle\ZaiBot\Service\AiResponder;
use Zephyrisle\ZaiBot\Service\LlmService;
use Zephyrisle\ZaiBot\Service\MemoryService;
use Zephyrisle\ZaiBot\Service\ProviderManager;
use Zephyrisle\ZaiBot\Service\SettingAccessor;
use Zephyrisle\ZaiBot\Service\StatsService;
use Zephyrisle\ZaiBot\Service\ToolExecutionService;
use Zephyrisle\ZaiBot\Support\DatabaseConfig;
use Zephyrisle\ZaiBot\Tool\ToolRegistry;

class ZaiBotServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(DatabaseConfig::class);
        $this->container->singleton(ToolRegistry::class);
        $this->container->singleton(Client::class, fn () => new Client());
        $this->container->singleton(SettingAccessor::class);
        $this->container->singleton(ProviderManager::class);
        $this->container->singleton(AgentManager::class);
        $this->container->singleton(StatsService::class);
        $this->container->singleton(LlmService::class);
        $this->container->singleton(MemoryService::class);
        $this->container->singleton(ToolExecutionService::class);
        $this->container->singleton(AiResponder::class);
    }

    public function boot(ToolRegistry $tools): void
    {
        $tools->register('like', [
            'dependency' => 'flarum-likes',
            'target_types' => ['post', 'discussion'],
            'dangerous' => false,
        ]);

        $tools->register('report', [
            'dependency' => 'flarum-flags',
            'target_types' => ['post', 'discussion', 'user'],
            'dangerous' => true,
        ]);

        $tools->register('reaction', [
            'dependency' => 'fof-reactions',
            'target_types' => ['post'],
            'dangerous' => false,
        ]);

        $tools->register('analyze_upload', [
            'dependency' => 'fof-upload',
            'target_types' => ['upload'],
            'dangerous' => false,
        ]);
    }
}
