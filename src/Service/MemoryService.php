<?php

namespace Zephyrisle\ZaiBot\Service;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Zephyrisle\ZaiBot\Model\AiAgent;
use Zephyrisle\ZaiBot\Model\ConversationMemory;
use Zephyrisle\ZaiBot\Model\UserAiMemory;

class MemoryService
{
    public function __construct(
        private SettingAccessor $settings,
        private LlmService $llm
    ) {
    }

    public function retrieveRelevantMemories(?int $userId, ?int $discussionId, ?int $agentId, string $query): array
    {
        if (! $this->settings->bool('long_term_memory_enabled')) {
            return [];
        }

        $embedding = $this->llm->embed(null, $query);
        $limit = max(1, $this->settings->int('memory_retrieval_limit', 5));

        $memories = ConversationMemory::query()
            ->when($userId, fn ($builder) => $builder->where(function ($queryBuilder) use ($userId) {
                $queryBuilder->where('user_id', $userId)->orWhereNull('user_id');
            }))
            ->when($discussionId, fn ($builder) => $builder->where(function ($queryBuilder) use ($discussionId) {
                $queryBuilder->where('discussion_id', $discussionId)->orWhereNull('discussion_id');
            }))
            ->when($agentId, fn ($builder) => $builder->where(function ($queryBuilder) use ($agentId) {
                $queryBuilder->where('ai_agent_id', $agentId)->orWhereNull('ai_agent_id');
            }))
            ->nearestTo($embedding, $limit)
            ->get();

        $now = now();
        foreach ($memories as $memory) {
            $memory->last_accessed_at = $now;
            $memory->strength = min(1.5, ((float) $memory->strength) + 0.02);
            $memory->save();
        }

        return $memories->map(fn (ConversationMemory $memory) => [
            'id' => (int) $memory->id,
            'summary' => $memory->summary,
            'strength' => (float) $memory->strength,
            'distance' => (float) ($memory->neighbor_distance ?? 0.0),
            'discussionId' => $memory->discussion_id ? (int) $memory->discussion_id : null,
            'userId' => $memory->user_id ? (int) $memory->user_id : null,
        ])->all();
    }

    public function storeSummary(?int $userId, ?int $discussionId, ?AiAgent $agent, string $summary, array $metadata = []): ConversationMemory
    {
        $memory = new ConversationMemory();
        $memory->user_id = $userId;
        $memory->discussion_id = $discussionId;
        $memory->ai_agent_id = $agent?->id;
        $memory->summary = trim($summary);
        $memory->embedding = $this->llm->embed($agent?->provider, $summary, $agent?->embedding_model);
        $memory->strength = (float) Arr::get($metadata, 'strength', $this->settings->float('memory_initial_strength', 1.0));
        $memory->last_accessed_at = now();
        $memory->save();

        if ($userId && $agent) {
            $this->touchUserProfile(
                $userId,
                $agent,
                (float) Arr::get($metadata, 'affectionDelta', 0.02),
                Arr::get($metadata, 'personalityTags', [])
            );
        }

        return $memory->refresh();
    }

    public function touchUserProfile(int $userId, AiAgent $agent, float $affectionDelta = 0.0, array $personalityTags = []): UserAiMemory
    {
        $profile = UserAiMemory::query()->firstOrNew([
            'user_id' => $userId,
            'ai_agent_id' => $agent->id,
        ]);

        $current = (float) ($profile->affection_score ?? 0.5);
        $floor = $this->settings->float('affection_floor', 0.3);
        $positiveCap = $this->settings->float('affection_max_positive_delta', 0.05);
        $negativeCap = $this->settings->float('affection_max_negative_delta', 0.1);
        $delta = max(-$negativeCap, min($positiveCap, $affectionDelta));

        $existingTags = array_values(array_unique(array_filter((array) ($profile->personality_tags ?? []))));
        $mergedTags = array_values(array_unique(array_filter(array_merge($existingTags, $personalityTags))));

        $profile->affection_score = max($floor, min(1.0, $current + $delta));
        $profile->personality_tags = $mergedTags;
        $profile->interaction_count = (int) ($profile->interaction_count ?? 0) + 1;
        $profile->last_interaction = now();
        $profile->save();

        return $profile->refresh();
    }

    public function decayAll(): array
    {
        $rate = $this->settings->float('memory_decay_rate', 0.01);
        $threshold = $this->settings->float('memory_cleanup_threshold', 0.1);

        $decayed = 0;
        $deleted = 0;

        ConversationMemory::query()->orderBy('id')->chunkById(200, function ($memories) use ($rate, $threshold, &$decayed, &$deleted) {
            foreach ($memories as $memory) {
                $memory->strength = max(0.0, ((float) $memory->strength) - $rate);

                if ($memory->strength < $threshold) {
                    $memory->delete();
                    $deleted++;
                    continue;
                }

                $memory->save();
                $decayed++;
            }
        });

        if ($this->settings->bool('affection_decay_enabled')) {
            $this->decayAffection();
        }

        return ['decayed' => $decayed, 'deleted' => $deleted];
    }

    public function cleanupSessionState(?CarbonInterface $before = null): int
    {
        return DB::table('ai_session_state')
            ->where('expires_at', '<', ($before ?: now())->toDateTimeString())
            ->delete();
    }

    public function updatePersonalityTags(): int
    {
        $updated = 0;

        UserAiMemory::query()->with('agent')->chunkById(100, function ($profiles) use (&$updated) {
            foreach ($profiles as $profile) {
                $memories = ConversationMemory::query()
                    ->where('user_id', $profile->user_id)
                    ->where('ai_agent_id', $profile->ai_agent_id)
                    ->latest()
                    ->limit(10)
                    ->pluck('summary')
                    ->all();

                $tags = $this->inferTags(implode("\n", $memories));
                $profile->personality_tags = array_values(array_unique(array_filter(array_merge((array) $profile->personality_tags, $tags))));
                $profile->save();
                $updated++;
            }
        });

        return $updated;
    }

    private function decayAffection(): void
    {
        $rate = $this->settings->float('affection_decay_rate', 0.001);
        $floor = $this->settings->float('affection_floor', 0.3);

        UserAiMemory::query()->chunkById(200, function ($profiles) use ($rate, $floor) {
            foreach ($profiles as $profile) {
                $profile->affection_score = max($floor, ((float) $profile->affection_score) - $rate);
                $profile->save();
            }
        });
    }

    private function inferTags(string $text): array
    {
        $normalized = mb_strtolower($text);
        $map = [
            'friendly' => ['谢谢', '感谢', 'nice', 'helpful', 'support'],
            'technical' => ['php', '代码', 'debug', 'api', '数据库'],
            'creative' => ['故事', '设定', 'creative', 'world', 'idea'],
            'careful' => ['风险', '安全', '谨慎', 'review', 'error'],
        ];

        $tags = [];
        foreach ($map as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, mb_strtolower($keyword))) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return $tags;
    }
}
