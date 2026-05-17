<?php

namespace Zephyrisle\ZaiBot\Console;

use Flarum\Console\AbstractCommand;
use Zephyrisle\ZaiBot\Service\MemoryService;

class UpdatePersonalityTagsCommand extends AbstractCommand
{
    public function __construct(private MemoryService $memory)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('zai-bot:update-personality-tags')
            ->setDescription('Refresh user personality tags from stored memories.');
    }

    protected function fire()
    {
        $updated = $this->memory->updatePersonalityTags();
        $this->info('Updated '.$updated.' user personality profiles.');

        return 0;
    }
}
