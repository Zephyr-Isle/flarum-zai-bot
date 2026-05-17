<?php

namespace Zephyrisle\ZaiBot\Console;

use Flarum\Console\AbstractCommand;
use Zephyrisle\ZaiBot\Model\AiActionLog;

class CleanupActionLogsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('zai-bot:cleanup-action-logs')
            ->setDescription('Delete old AI action logs.');
    }

    protected function fire()
    {
        $deleted = AiActionLog::query()
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        $this->info('Deleted '.$deleted.' old action logs.');

        return 0;
    }
}
