<?php

namespace Zephyrisle\ZaiBot\Console;

use Flarum\Console\AbstractCommand;
use Zephyrisle\ZaiBot\Service\MemoryService;

class DecayMemoryStrengthCommand extends AbstractCommand
{
    public function __construct(private MemoryService $memory)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('zai-bot:decay-memories')
            ->setDescription('Apply decay rules to long-term memories and affection scores.');
    }

    protected function fire()
    {
        $result = $this->memory->decayAll();
        $this->info(sprintf('Decayed %d memories, deleted %d.', $result['decayed'], $result['deleted']));

        return 0;
    }
}
