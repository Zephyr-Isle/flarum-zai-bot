<?php

namespace Zephyrisle\ZaiBot\Service;

use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Zephyrisle\ZaiBot\Model\AiActionLog;
use Zephyrisle\ZaiBot\Model\AiAgent;
use Zephyrisle\ZaiBot\Tool\ToolRegistry;

class ToolExecutionService
{
    public function __construct(
        private SettingAccessor $settings,
        private ToolRegistry $tools,
        private ExtensionIntegrationService $integrations
    ) {
    }

    public function suggestActionsFromReply(string $reply, Post $sourcePost): array
    {
        $actions = [];
        $normalized = mb_strtolower($reply);

        if ($this->settings->bool('tool_like_enabled')
            && preg_match('/(谢谢|很棒|赞同|感谢|喜欢|great|thanks|helpful)/u', $normalized) === 1) {
            $actions[] = [
                'tool' => 'like',
                'targetType' => 'post',
                'targetId' => (int) $sourcePost->id,
            ];
        }

        return $actions;
    }

    public function executeBatch(AiAgent $agent, array $actions, ?User $triggerUser = null): array
    {
        $results = [];

        foreach ($actions as $action) {
            $results[] = $this->execute($agent, $action, $triggerUser);
        }

        return $results;
    }

    public function execute(AiAgent $agent, array $action, ?User $triggerUser = null): array
    {
        $tool = (string) Arr::get($action, 'tool');
        $targetType = (string) Arr::get($action, 'targetType', 'post');
        $targetId = Arr::get($action, 'targetId');

        if ($tool === '' || ! $this->tools->isAvailable($tool)) {
            return $this->logResult($agent, $triggerUser, $tool ?: 'unknown', $targetType, $targetId, 'denied', 'Tool unavailable.');
        }

        return match ($tool) {
            'like' => $this->like($agent, $targetType, $targetId, $triggerUser),
            'report' => $this->report($agent, $targetType, $targetId, $triggerUser, $action),
            'reaction' => $this->reaction($agent, $targetType, $targetId, $triggerUser, $action),
            'analyze_upload' => $this->logResult($agent, $triggerUser, 'analyze_upload', $targetType, $targetId, 'denied', 'Upload analysis requires optional integration.'),
            'tag_discussion', 'approve_content', 'lock_discussion', 'sticky_discussion', 'mark_best_answer', 'follow_discussion', 'follow_tag', 'message_user', 'start_private_discussion' => $this->reservedIntegrationAction($agent, $triggerUser, $tool, $targetType, $targetId),
            default => $this->logResult($agent, $triggerUser, $tool, $targetType, $targetId, 'denied', 'Unknown tool requested.'),
        };
    }

    private function reservedIntegrationAction(AiAgent $agent, ?User $triggerUser, string $tool, string $targetType, mixed $targetId): array
    {
        $definition = $this->integrations->toolDefinitions()[$tool] ?? [];
        $dependency = (string) ($definition['dependency'] ?? '');
        $message = $dependency !== ''
            ? sprintf('Tool recognized for %s, but automatic execution is not implemented yet.', $dependency)
            : 'Tool recognized, but automatic execution is not implemented yet.';

        return $this->logResult($agent, $triggerUser, $tool, $targetType, $targetId, 'denied', $message);
    }

    private function like(AiAgent $agent, string $targetType, mixed $targetId, ?User $triggerUser): array
    {
        if (! $this->settings->bool('tool_like_enabled')) {
            return $this->logResult($agent, $triggerUser, 'like', $targetType, $targetId, 'denied', 'Like tool disabled.');
        }

        if ($targetType !== 'post' || ! Schema::hasTable('post_likes')) {
            return $this->logResult($agent, $triggerUser, 'like', $targetType, $targetId, 'denied', 'Like target not supported.');
        }

        $post = Post::query()->find($targetId);
        if (! $post) {
            return $this->logResult($agent, $triggerUser, 'like', 'post', $targetId, 'failed', 'Post not found.');
        }

        $alreadyLiked = DB::table('post_likes')
            ->where('post_id', $post->id)
            ->where('user_id', $agent->flarum_user_id)
            ->exists();

        if ($alreadyLiked) {
            return $this->logResult($agent, $triggerUser, 'like', 'post', $post->id, 'success', 'Already liked.');
        }

        DB::table('post_likes')->insert([
            'post_id' => $post->id,
            'user_id' => $agent->flarum_user_id,
        ]);

        if (Schema::hasColumn('posts', 'likes_count')) {
            DB::table('posts')->where('id', $post->id)->increment('likes_count');
        }

        return $this->logResult($agent, $triggerUser, 'like', 'post', $post->id, 'success');
    }

    private function report(AiAgent $agent, string $targetType, mixed $targetId, ?User $triggerUser, array $action): array
    {
        if (! $this->settings->bool('tool_report_enabled')) {
            return $this->logResult($agent, $triggerUser, 'report', $targetType, $targetId, 'denied', 'Report tool disabled.');
        }

        if ($targetType !== 'post' || ! Schema::hasTable('flags')) {
            return $this->logResult($agent, $triggerUser, 'report', $targetType, $targetId, 'denied', 'Flag table unavailable.');
        }

        $threshold = max(1, $this->settings->int('tool_report_confirmation_threshold', 2));
        $confidence = (int) Arr::get($action, 'confidence', 1);
        if ($confidence < $threshold) {
            return $this->logResult($agent, $triggerUser, 'report', 'post', $targetId, 'denied', 'Report confirmation threshold not met.');
        }

        $post = Post::query()->find($targetId);
        if (! $post) {
            return $this->logResult($agent, $triggerUser, 'report', 'post', $targetId, 'failed', 'Post not found.');
        }

        $exists = DB::table('flags')
            ->where('post_id', $post->id)
            ->where('user_id', $agent->flarum_user_id)
            ->exists();

        if (! $exists) {
            DB::table('flags')->insert([
                'post_id' => $post->id,
                'type' => Arr::get($action, 'type', 'custom'),
                'user_id' => $agent->flarum_user_id,
                'reason' => Arr::get($action, 'reason', 'AI moderation concern'),
                'reason_detail' => Arr::get($action, 'detail', 'Flagged by Zai Bot tool pipeline.'),
                'created_at' => now(),
            ]);
        }

        return $this->logResult($agent, $triggerUser, 'report', 'post', $post->id, 'success');
    }

    private function reaction(AiAgent $agent, string $targetType, mixed $targetId, ?User $triggerUser, array $action): array
    {
        if (! $this->settings->bool('tool_follow_enabled')) {
            return $this->logResult($agent, $triggerUser, 'reaction', $targetType, $targetId, 'denied', 'Reaction tool disabled.');
        }

        if ($targetType !== 'post') {
            return $this->logResult($agent, $triggerUser, 'reaction', $targetType, $targetId, 'denied', 'Reaction target not supported.');
        }

        $postReactionsTable = Schema::hasTable('post_reactions')
            ? 'post_reactions'
            : (Schema::hasTable('fof_reactions_post_reactions') ? 'fof_reactions_post_reactions' : null);
        $reactionsTable = Schema::hasTable('reactions')
            ? 'reactions'
            : (Schema::hasTable('fof_reactions_reactions') ? 'fof_reactions_reactions' : null);

        if (! $postReactionsTable || ! $reactionsTable) {
            return $this->logResult($agent, $triggerUser, 'reaction', 'post', $targetId, 'denied', 'Reaction tables unavailable.');
        }

        $reactionId = Arr::get($action, 'reactionId')
            ?: DB::table($reactionsTable)->orderBy('id')->value('id');

        if (! $reactionId) {
            return $this->logResult($agent, $triggerUser, 'reaction', 'post', $targetId, 'failed', 'No reaction type configured.');
        }

        DB::table($postReactionsTable)->updateOrInsert(
            ['post_id' => $targetId, 'user_id' => $agent->flarum_user_id],
            ['reaction_id' => $reactionId]
        );

        return $this->logResult($agent, $triggerUser, 'reaction', 'post', $targetId, 'success');
    }

    private function logResult(
        AiAgent $agent,
        ?User $triggerUser,
        string $actionType,
        string $targetType,
        mixed $targetId,
        string $result,
        ?string $errorMessage = null
    ): array {
        AiActionLog::query()->create([
            'ai_agent_id' => $agent->id,
            'user_id' => $triggerUser?->id,
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId ? (int) $targetId : null,
            'result' => $result,
            'error_message' => $errorMessage,
            'created_at' => now(),
        ]);

        return [
            'tool' => $actionType,
            'targetType' => $targetType,
            'targetId' => $targetId ? (int) $targetId : null,
            'result' => $result,
            'message' => $errorMessage,
        ];
    }
}
