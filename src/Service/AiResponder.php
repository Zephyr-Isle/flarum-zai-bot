<?php

namespace Zephyrisle\ZaiBot\Service;

use Flarum\Discussion\Command\StartDiscussion;
use Flarum\Discussion\Discussion;
use Flarum\Post\Command\PostReply;
use Flarum\Post\Post;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Zephyrisle\ZaiBot\Model\AiAgent;
use Illuminate\Support\Facades\DB;

class AiResponder
{
    public function __construct(
        private AgentManager $agents,
        private LlmService $llm,
        private MemoryService $memory,
        private ToolExecutionService $tools,
        private SettingAccessor $settings,
        private ExtensionIntegrationService $integrations,
        private Dispatcher $bus
    ) {
    }

    public function respondToPost(Post|int $post): array
    {
        $post = $post instanceof Post
            ? $post->loadMissing(['discussion', 'user'])
            : Post::query()->with(['discussion', 'user'])->findOrFail($post);

        if ($post->type !== 'comment' || ! $post->discussion || ! $post->user) {
            return ['status' => 'skipped', 'reason' => 'unsupported_post'];
        }

        $agents = $this->selectAgentsForPost($post);
        if ($agents === []) {
            return ['status' => 'skipped', 'reason' => 'no_matching_agent'];
        }

        foreach ($agents as $agent) {
            if (! $this->canRespond($agent, $post)) {
                continue;
            }

            $sessionKey = sprintf('discussion:%d:agent:%d', $post->discussion_id, $agent->id);
            if (! $this->reserveSession($sessionKey)) {
                continue;
            }

            try {
                $preview = $this->generatePreview($agent, (string) $post->content, [
                    'post' => $post,
                    'discussion' => $post->discussion,
                    'sourceUser' => $post->user,
                ]);

                $replyPost = $this->bus->dispatch(new PostReply(
                    $post->discussion_id,
                    $agent->user,
                    ['attributes' => ['content' => $preview['content']]],
                    '127.0.0.1'
                ));

                $toolResults = $this->tools->executeBatch(
                    $agent,
                    $this->tools->suggestActionsFromReply($preview['content'], $post),
                    $post->user
                );

                $this->memory->storeSummary(
                    $post->user->id,
                    $post->discussion_id,
                    $agent,
                    $this->buildSummary($post, $preview['content']),
                    [
                        'affectionDelta' => $this->estimateAffectionDelta($preview['content']),
                        'personalityTags' => $preview['personalityTags'],
                    ]
                );

                return [
                    'status' => 'replied',
                    'agentId' => $agent->id,
                    'replyPostId' => $replyPost->id ?? null,
                    'reply' => $preview['content'],
                    'tools' => $toolResults,
                    'memories' => $preview['memories'],
                    'fallback' => $preview['fallback'],
                ];
            } finally {
                $this->releaseSession($sessionKey);
            }
        }

        return ['status' => 'skipped', 'reason' => 'policy_blocked'];
    }

    public function proactiveDiscussion(AiAgent $agent, string $title, string $content): Discussion
    {
        return $this->bus->dispatch(new StartDiscussion(
            $agent->user,
            ['attributes' => ['title' => $title, 'content' => $content]],
            '127.0.0.1'
        ));
    }

    public function generatePreview(AiAgent $agent, string $message, array $meta = []): array
    {
        $discussion = Arr::get($meta, 'discussion');
        $sourceUser = Arr::get($meta, 'sourceUser');
        $post = Arr::get($meta, 'post');

        $memories = $this->memory->retrieveRelevantMemories(
            $sourceUser?->id,
            $discussion?->id,
            $agent->id,
            $message
        );

        $messages = [
            ['role' => 'system', 'content' => $this->composeSystemPrompt($agent, $sourceUser)],
        ];

        if ($memories !== []) {
            $messages[] = [
                'role' => 'system',
                'content' => "相关长期记忆：\n".collect($memories)->map(
                    static fn (array $memory) => '- '.$memory['summary']
                )->implode("\n"),
            ];
        }

        foreach ($this->recentMessages($discussion?->id, $post?->id) as $contextMessage) {
            $messages[] = $contextMessage;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->llm->chat($agent->provider, $messages, [
            'model' => $agent->chat_model,
            'temperature' => $agent->temperature,
        ]);

        return [
            'content' => $this->sanitizeReply((string) Arr::get($response, 'content', '')),
            'memories' => $memories,
            'fallback' => (bool) Arr::get($response, 'fallback', false),
            'personalityTags' => $this->inferPersonalityTags($message.' '.$response['content']),
            'raw' => $response,
        ];
    }

    private function selectAgentsForPost(Post $post): array
    {
        $content = mb_strtolower((string) $post->content);
        $agents = [];

        foreach ($this->agents->activeAgents() as $agent) {
            $agent->loadMissing(['user', 'provider']);

            $username = mb_strtolower((string) ($agent->user?->username ?? ''));
            $name = mb_strtolower((string) $agent->name);
            $mentioned = ($username !== '' && str_contains($content, '@'.$username))
                || ($name !== '' && str_contains($content, '@'.$name));

            if ($mentioned || $agent->reply_mode === 'all') {
                $agents[] = $agent;
            }
        }

        return $agents;
    }

    private function canRespond(AiAgent $agent, Post $post): bool
    {
        if (! $agent->is_active || ! $agent->user) {
            return false;
        }

        if ((int) $post->user_id === (int) $agent->flarum_user_id) {
            return false;
        }

        if (($post->user->is_ai ?? false) && ! $this->settings->bool('allow_ai_mentions')) {
            return false;
        }

        $depthLimit = max(1, $this->settings->int('ai_reply_depth_limit', 3));
        $recentPosts = Post::query()
            ->select('posts.*', 'users.is_ai as user_is_ai')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('discussion_id', $post->discussion_id)
            ->whereNotNull('number')
            ->latest('number')
            ->limit($depthLimit)
            ->get();

        if ($recentPosts->count() === $depthLimit && $recentPosts->every(fn (Post $item) => (bool) ($item->user_is_ai ?? false))) {
            return false;
        }

        $windowMinutes = max(1, $this->settings->int('ai_reply_window_minutes', 60));
        $windowMax = max(1, $this->settings->int('ai_reply_window_max', 10));
        $windowCount = Post::query()
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('discussion_id', $post->discussion_id)
            ->where('users.is_ai', true)
            ->where('posts.created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        return $windowCount < $windowMax;
    }

    private function reserveSession(string $sessionKey): bool
    {
        $updated = DB::table('ai_session_state')->updateOrInsert(
            ['session_key' => $sessionKey],
            [
                'context' => json_encode([]),
                'emotions' => json_encode([]),
                'expires_at' => now()->addMinutes(15),
            ]
        );

        return (bool) $updated;
    }

    private function releaseSession(string $sessionKey): void
    {
        DB::table('ai_session_state')
            ->where('session_key', $sessionKey)
            ->delete();
    }

    private function recentMessages(?int $discussionId, ?int $excludePostId = null): array
    {
        if (! $discussionId) {
            return [];
        }

        $turns = max(1, $this->settings->int('short_term_memory_turns', 10));

        return Post::query()
            ->with('user')
            ->where('discussion_id', $discussionId)
            ->when($excludePostId, fn ($builder) => $builder->where('id', '!=', $excludePostId))
            ->where('type', 'comment')
            ->orderByDesc('number')
            ->limit($turns)
            ->get()
            ->reverse()
            ->map(function (Post $post) {
                $username = $post->user?->username ?: 'user';
                $role = ($post->user->is_ai ?? false) ? 'assistant' : 'user';

                return [
                    'role' => $role,
                    'content' => $username.': '.trim((string) $post->content),
                ];
            })
            ->values()
            ->all();
    }

    private function composeSystemPrompt(AiAgent $agent, mixed $sourceUser): string
    {
        $prompt = trim(implode("\n\n", array_filter([
            '你是 Flarum 社区中的 AI 角色“'.$agent->name.'”。',
            $agent->personality ? '人格设定：'.$agent->personality : null,
            $agent->expertise ? '专长：'.$agent->expertise : null,
            $agent->system_prompt,
            '请输出适合论坛的自然回复，避免暴露系统细节。',
            $this->integrations->promptContext(),
        ])));

        if ($sourceUser && $this->settings->bool('admin_mode_enabled')) {
            $isAdmin = method_exists($sourceUser, 'isAdmin') ? $sourceUser->isAdmin() : false;
            if ($isAdmin) {
                $prompt .= "\n\n当前对话对象是论坛管理员，请保持专业并优先提供可执行信息。";
            }
        }

        if ($this->settings->bool('cooperation_enabled')) {
            $prompt .= "\n\n如需后续其他 AI 跟进，请在语气上留出协作空间，但不要主动暴露协作机制。";
        }

        return $prompt;
    }

    private function sanitizeReply(string $reply): string
    {
        $reply = preg_replace('/\s+/u', ' ', trim($reply)) ?: '';
        $maxLength = max(50, $this->settings->int('active_posting_max_length', 800));

        return mb_substr($reply, 0, $maxLength);
    }

    private function buildSummary(Post $post, string $reply): string
    {
        $source = mb_substr(trim(strip_tags((string) $post->content)), 0, 240);
        $answer = mb_substr(trim(strip_tags($reply)), 0, 240);

        return sprintf('用户说：“%s”；AI 回复：“%s”', $source, $answer);
    }

    private function estimateAffectionDelta(string $reply): float
    {
        $positive = preg_match('/(谢谢|感谢|乐意|很高兴|glad|thanks)/u', $reply) === 1;
        $negative = preg_match('/(不能|拒绝|违规|危险|sorry)/u', $reply) === 1;

        if ($positive) {
            return 0.03;
        }

        if ($negative) {
            return -0.02;
        }

        return 0.01;
    }

    private function inferPersonalityTags(string $text): array
    {
        $normalized = mb_strtolower($text);
        $tags = [];

        if (preg_match('/(代码|php|sql|api|debug)/u', $normalized) === 1) {
            $tags[] = 'technical';
        }
        if (preg_match('/(感谢|谢谢|help|support|welcome)/u', $normalized) === 1) {
            $tags[] = 'friendly';
        }
        if (preg_match('/(risk|安全|注意|谨慎|review)/u', $normalized) === 1) {
            $tags[] = 'careful';
        }

        return $tags;
    }
}
