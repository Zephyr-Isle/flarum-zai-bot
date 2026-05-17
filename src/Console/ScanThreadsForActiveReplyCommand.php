<?php

namespace Zephyrisle\ZaiBot\Console;

use Flarum\Console\AbstractCommand;
use Illuminate\Support\Str;
use Zephyrisle\ZaiBot\Service\AgentManager;
use Zephyrisle\ZaiBot\Service\AiResponder;
use Zephyrisle\ZaiBot\Service\SettingAccessor;

class ScanThreadsForActiveReplyCommand extends AbstractCommand
{
    public function __construct(
        private AgentManager $agents,
        private AiResponder $responder,
        private SettingAccessor $settings
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('zai-bot:scan-threads')
            ->setDescription('Scan the forum and optionally let an AI start a proactive discussion.');
    }

    protected function fire()
    {
        if (! $this->settings->bool('active_posting_enabled')) {
            $this->info('Active posting disabled.');

            return 0;
        }

        $agents = $this->agents->activeAgents();
        if ($agents === []) {
            $this->info('No active AI agents available.');

            return 0;
        }

        $probability = max(0, min(100, $this->settings->int('active_posting_probability', 5)));
        if (random_int(1, 100) > $probability) {
            $this->info('Probability gate skipped this cycle.');

            return 0;
        }

        $agent = $agents[array_rand($agents)];
        $title = 'AI Lounge Check-in '.now()->format('Y-m-d H:i');
        $content = Str::limit(
            $this->responder->generatePreview($agent, '请你为论坛生成一段自然、友好的暖场帖，邀请大家继续交流。')['content'],
            $this->settings->int('active_posting_max_length', 800)
        );

        $discussion = $this->responder->proactiveDiscussion($agent, $title, $content);
        $this->info('Created discussion #'.$discussion->id.' with agent #'.$agent->id);

        return 0;
    }
}
