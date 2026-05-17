<?php

namespace Zephyrisle\ZaiBot\Service;

use Flarum\User\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Zephyrisle\ZaiBot\Model\AiAgent;

class AgentManager
{
    public function __construct(private Hasher $hasher)
    {
    }

    public function list(): array
    {
        return AiAgent::query()->with(['user', 'provider'])->orderByDesc('is_active')->orderBy('id')->get()->map(
            fn (AiAgent $agent) => $this->serialize($agent)
        )->all();
    }

    public function find(int $id): AiAgent
    {
        return AiAgent::query()->with(['user', 'provider'])->findOrFail($id);
    }

    public function activeAgents(): array
    {
        return AiAgent::query()->with(['user', 'provider'])->where('is_active', true)->get()->all();
    }

    public function create(array $attributes): AiAgent
    {
        $agent = new AiAgent();

        return $this->save($agent, $attributes);
    }

    public function update(AiAgent $agent, array $attributes): AiAgent
    {
        return $this->save($agent, $attributes);
    }

    public function delete(AiAgent $agent, bool $deleteUser = false): void
    {
        $user = $agent->user;
        $agent->delete();

        if ($deleteUser && $user) {
            $user->delete();
        }
    }

    public function serialize(AiAgent $agent): array
    {
        $agent->loadMissing(['user', 'provider']);

        return [
            'id' => (int) $agent->id,
            'flarumUserId' => (int) $agent->flarum_user_id,
            'providerId' => $agent->provider_id ? (int) $agent->provider_id : null,
            'name' => $agent->name,
            'avatarUrl' => $agent->avatar_url,
            'personality' => $agent->personality,
            'expertise' => $agent->expertise,
            'systemPrompt' => $agent->system_prompt,
            'temperature' => (float) $agent->temperature,
            'isActive' => (bool) $agent->is_active,
            'replyMode' => $agent->reply_mode,
            'activeTags' => $agent->active_tags ?? [],
            'cooperationRole' => $agent->cooperation_role,
            'hourlyPostLimit' => $agent->hourly_post_limit,
            'dailyPostLimit' => $agent->daily_post_limit,
            'chatModel' => $agent->chat_model,
            'visionModel' => $agent->vision_model,
            'embeddingModel' => $agent->embedding_model,
            'language' => $agent->language,
            'user' => $agent->user ? [
                'id' => (int) $agent->user->id,
                'username' => $agent->user->username,
                'email' => $agent->user->email,
                'isAi' => (bool) ($agent->user->is_ai ?? false),
            ] : null,
            'provider' => $agent->provider ? [
                'id' => (int) $agent->provider->id,
                'name' => $agent->provider->name,
            ] : null,
            'createdAt' => optional($agent->created_at)?->toAtomString(),
            'updatedAt' => optional($agent->updated_at)?->toAtomString(),
        ];
    }

    private function save(AiAgent $agent, array $attributes): AiAgent
    {
        $userId = Arr::get($attributes, 'flarumUserId');
        $activeTags = Arr::get($attributes, 'activeTags', $agent->active_tags ?? []);

        if (is_string($activeTags)) {
            $decoded = json_decode($activeTags, true);
            $activeTags = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $activeTags) ?: [])));
        }

        if (! $userId) {
            $userId = $this->createAiUser(
                Arr::get($attributes, 'username', Arr::get($attributes, 'name', 'zai-bot')),
                Arr::get($attributes, 'email')
            )->id;
        }

        $agent->flarum_user_id = $userId;
        $agent->provider_id = Arr::get($attributes, 'providerId');
        $agent->name = Arr::get($attributes, 'name', $agent->name);
        $agent->avatar_url = Arr::get($attributes, 'avatarUrl', $agent->avatar_url);
        $agent->personality = Arr::get($attributes, 'personality', $agent->personality ?? 'Helpful AI forum member.');
        $agent->expertise = Arr::get($attributes, 'expertise', $agent->expertise);
        $agent->system_prompt = Arr::get($attributes, 'systemPrompt', $agent->system_prompt);
        $agent->temperature = (float) Arr::get($attributes, 'temperature', $agent->temperature ?? 0.7);
        $agent->is_active = filter_var(Arr::get($attributes, 'isActive', $agent->is_active ?? true), FILTER_VALIDATE_BOOL);
        $agent->reply_mode = Arr::get($attributes, 'replyMode', $agent->reply_mode ?? 'mention');
        $agent->active_tags = $activeTags;
        $agent->cooperation_role = Arr::get($attributes, 'cooperationRole', $agent->cooperation_role ?? 'none');
        $agent->hourly_post_limit = Arr::get($attributes, 'hourlyPostLimit', $agent->hourly_post_limit);
        $agent->daily_post_limit = Arr::get($attributes, 'dailyPostLimit', $agent->daily_post_limit);
        $agent->chat_model = Arr::get($attributes, 'chatModel', $agent->chat_model);
        $agent->vision_model = Arr::get($attributes, 'visionModel', $agent->vision_model);
        $agent->embedding_model = Arr::get($attributes, 'embeddingModel', $agent->embedding_model);
        $agent->language = Arr::get($attributes, 'language', $agent->language ?? 'zh-CN');
        $agent->save();

        return $agent->refresh()->load(['user', 'provider']);
    }

    private function createAiUser(string $preferredName, ?string $email = null): User
    {
        $base = Str::slug($preferredName, '_') ?: 'zai_bot';
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base.'_'.$suffix;
        }

        $user = new User();
        $user->username = $username;
        $user->email = $email ?: 'ai_'.$username.'@local';
        $user->password = $this->hasher->make(Str::random(32));
        $user->is_email_confirmed = true;
        $user->is_ai = true;
        $user->joined_at = now();
        $user->save();

        return $user;
    }
}
