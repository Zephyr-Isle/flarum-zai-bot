<?php

namespace Zephyrisle\ZaiBot\Console;

use Flarum\Console\AbstractCommand;
use Zephyrisle\ZaiBot\Service\MemoryService;

class CleanupSessionStateCommand extends AbstractCommand
{
    public function __construct(private MemoryService $memory)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('zai-bot:cleanup-sessions')
            ->setDescription('Remove expired AI session state records.');
    }

    protected function fire()
    {
        $deleted = $this->memory->cleanupSessionState();
        $this->info('Deleted '.$deleted.' expired session rows.');

        return 0;
    }
}
