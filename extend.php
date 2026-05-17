<?php

/*
 * This file is part of zephyrisle/flarum-zai-bot.
 *
 * Copyright (c) 2026 zephyrisle.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Zephyrisle\ZaiBot;

use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Zephyrisle\ZaiBot\Console\CleanupActionLogsCommand;
use Zephyrisle\ZaiBot\Console\CleanupSessionStateCommand;
use Zephyrisle\ZaiBot\Console\DecayMemoryStrengthCommand;
use Zephyrisle\ZaiBot\Console\ScanThreadsForActiveReplyCommand;
use Zephyrisle\ZaiBot\Console\UpdatePersonalityTagsCommand;
use Zephyrisle\ZaiBot\Controller\Admin\DashboardController;
use Zephyrisle\ZaiBot\Controller\Admin\DeleteAgentController;
use Zephyrisle\ZaiBot\Controller\Admin\DeleteProviderController;
use Zephyrisle\ZaiBot\Controller\Admin\ListAgentsController;
use Zephyrisle\ZaiBot\Controller\Admin\ListProvidersController;
use Zephyrisle\ZaiBot\Controller\Admin\MaintenanceController;
use Zephyrisle\ZaiBot\Controller\Admin\SaveAgentController;
use Zephyrisle\ZaiBot\Controller\Admin\SaveProviderController;
use Zephyrisle\ZaiBot\Controller\Admin\TestChatController;
use Zephyrisle\ZaiBot\Controller\TestDatabaseConnectionController;
use Zephyrisle\ZaiBot\Listener\QueueAiResponseOnPost;
use Zephyrisle\ZaiBot\Provider\ZaiBotServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/resources/locale'),
    new Extend\ServiceProvider(ZaiBotServiceProvider::class),
    (new Extend\Routes('api'))
        ->post('/zai-bot/test-database', 'zai-bot.test-database', TestDatabaseConnectionController::class)
        ->get('/zai-bot/admin/dashboard', 'zai-bot.admin.dashboard', DashboardController::class)
        ->get('/zai-bot/admin/providers', 'zai-bot.admin.providers.index', ListProvidersController::class)
        ->post('/zai-bot/admin/providers', 'zai-bot.admin.providers.store', SaveProviderController::class)
        ->put('/zai-bot/admin/providers/{id:\d+}', 'zai-bot.admin.providers.update', SaveProviderController::class)
        ->delete('/zai-bot/admin/providers/{id:\d+}', 'zai-bot.admin.providers.delete', DeleteProviderController::class)
        ->get('/zai-bot/admin/agents', 'zai-bot.admin.agents.index', ListAgentsController::class)
        ->post('/zai-bot/admin/agents', 'zai-bot.admin.agents.store', SaveAgentController::class)
        ->put('/zai-bot/admin/agents/{id:\d+}', 'zai-bot.admin.agents.update', SaveAgentController::class)
        ->delete('/zai-bot/admin/agents/{id:\d+}', 'zai-bot.admin.agents.delete', DeleteAgentController::class)
        ->post('/zai-bot/admin/test-chat', 'zai-bot.admin.test-chat', TestChatController::class)
        ->post('/zai-bot/admin/maintenance', 'zai-bot.admin.maintenance', MaintenanceController::class),
    (new Extend\Event())
        ->listen(Posted::class, QueueAiResponseOnPost::class),
    (new Extend\Console())
        ->command(ScanThreadsForActiveReplyCommand::class)
        ->command(CleanupSessionStateCommand::class)
        ->command(DecayMemoryStrengthCommand::class)
        ->command(UpdatePersonalityTagsCommand::class)
        ->command(CleanupActionLogsCommand::class)
        ->schedule('zai-bot:scan-threads', function (ScheduledEvent $event) {
            $event->hourly()->withoutOverlapping();
        })
        ->schedule('zai-bot:cleanup-sessions', function (ScheduledEvent $event) {
            $event->everyThirtyMinutes()->withoutOverlapping();
        })
        ->schedule('zai-bot:decay-memories', function (ScheduledEvent $event) {
            $event->daily()->withoutOverlapping();
        })
        ->schedule('zai-bot:update-personality-tags', function (ScheduledEvent $event) {
            $event->dailyAt('03:00')->withoutOverlapping();
        })
        ->schedule('zai-bot:cleanup-action-logs', function (ScheduledEvent $event) {
            $event->weekly()->withoutOverlapping();
        }),
    (new Extend\Settings())
        ->serializeToForum('zaiBotAllowAiMentions', 'zai-bot.allow_ai_mentions', static function ($value) {
            return filter_var($value ?? false, FILTER_VALIDATE_BOOL);
        }, false)
        ->serializeToForum('zaiBotDeveloperMode', 'zai-bot.developer_mode', static function ($value) {
            return filter_var($value ?? false, FILTER_VALIDATE_BOOL);
        }, false)
        ->serializeToForum('zaiBotActivePostingEnabled', 'zai-bot.active_posting_enabled', static function ($value) {
            return filter_var($value ?? false, FILTER_VALIDATE_BOOL);
        }, false),
];
