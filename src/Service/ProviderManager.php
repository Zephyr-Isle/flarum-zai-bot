<?php

namespace Zephyrisle\ZaiBot\Service;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Arr;
use Zephyrisle\ZaiBot\Model\AiProvider;

class ProviderManager
{
    private const DRIVER_ALIASES = [
        'openai' => 'openai-compatible',
        'openai-compatible' => 'openai-compatible',
        'moonshot' => 'moonshot',
        'moonshotai' => 'moonshot',
        'glm' => 'glm',
        'zhipu' => 'glm',
        'minimax' => 'minimax',
        'qwen' => 'qwen',
        'dashscope' => 'qwen',
        'deepseek' => 'deepseek',
        'deepseek-openai' => 'deepseek',
        'deepseekopenai' => 'deepseek',
        'anthropic' => 'anthropic',
        'claude' => 'anthropic',
        'google' => 'google',
        'gemini' => 'google',
        'openrouter' => 'openrouter',
    ];

    private const DEFAULT_BASE_URLS = [
        'openai-compatible' => 'https://api.openai.com/v1',
        'moonshot' => 'https://api.moonshot.cn/v1',
        'glm' => 'https://open.bigmodel.cn/api/paas/v4',
        'minimax' => 'https://api.minimax.chat/v1',
        'qwen' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
        'deepseek' => 'https://api.deepseek.com/v1',
        'anthropic' => 'https://api.anthropic.com/v1',
        'google' => 'https://generativelanguage.googleapis.com/v1beta',
        'openrouter' => 'https://openrouter.ai/api/v1',
    ];

    public function __construct(private Encrypter $encrypter)
    {
    }

    public function list(): array
    {
        return AiProvider::query()->orderByDesc('is_active')->orderBy('name')->get()->map(
            fn (AiProvider $provider) => $this->serialize($provider)
        )->all();
    }

    public function find(int $id): AiProvider
    {
        return AiProvider::query()->findOrFail($id);
    }

    public function firstActive(): ?AiProvider
    {
        return AiProvider::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function create(array $attributes): AiProvider
    {
        return $this->save(new AiProvider(), $attributes);
    }

    public function update(AiProvider $provider, array $attributes): AiProvider
    {
        return $this->save($provider, $attributes);
    }

    public function delete(AiProvider $provider): void
    {
        $provider->delete();
    }

    public function serialize(AiProvider $provider, bool $includeSecret = false): array
    {
        $driver = $this->normalizeDriver($provider->driver);

        return [
            'id' => (int) $provider->id,
            'name' => $provider->name,
            'driver' => $driver,
            'baseUrl' => $provider->base_url,
            'models' => $provider->models ?? [],
            'isActive' => (bool) $provider->is_active,
            'apiKeyConfigured' => ! empty($provider->api_key_encrypted),
            'apiKey' => $includeSecret ? $this->decrypt($provider->api_key_encrypted) : null,
            'createdAt' => optional($provider->created_at)?->toAtomString(),
            'updatedAt' => optional($provider->updated_at)?->toAtomString(),
        ];
    }

    public function makeDraft(array $attributes, ?AiProvider $source = null): AiProvider
    {
        $provider = new AiProvider();

        if ($source !== null) {
            $provider->id = $source->id;
            $provider->name = $source->name;
            $provider->driver = $source->driver;
            $provider->base_url = $source->base_url;
            $provider->api_key_encrypted = $source->api_key_encrypted;
            $provider->models = $source->models;
            $provider->is_active = $source->is_active;
        }

        return $this->fill($provider, $attributes);
    }

    private function save(AiProvider $provider, array $attributes): AiProvider
    {
        $provider = $this->fill($provider, $attributes);

        $provider->save();

        return $provider->refresh();
    }

    private function fill(AiProvider $provider, array $attributes): AiProvider
    {
        $driver = $this->normalizeDriver(Arr::get($attributes, 'driver', $provider->driver ?: 'openai-compatible'));
        $baseUrl = trim((string) Arr::get($attributes, 'baseUrl', $provider->base_url ?? ''));

        $provider->name = trim((string) Arr::get($attributes, 'name', $provider->name ?? ''));
        $provider->driver = $driver;
        $provider->base_url = rtrim($baseUrl !== '' ? $baseUrl : $this->defaultBaseUrl($driver), '/');
        $provider->models = $this->normalizeModels(Arr::get($attributes, 'models', $provider->models ?? []));
        $provider->is_active = filter_var(Arr::get($attributes, 'isActive', $provider->is_active ?? true), FILTER_VALIDATE_BOOL);

        $apiKey = Arr::get($attributes, 'apiKey');
        if (is_string($apiKey)) {
            $apiKey = trim($apiKey);

            if ($apiKey !== '') {
                $provider->api_key_encrypted = $this->encrypter->encryptString($apiKey);
            } elseif (! $provider->exists && empty($provider->api_key_encrypted)) {
                $provider->api_key_encrypted = '';
            }
        } elseif (! $provider->exists && empty($provider->api_key_encrypted)) {
            $provider->api_key_encrypted = '';
        }

        return $provider;
    }

    private function normalizeModels(mixed $models): array
    {
        if (is_string($models)) {
            $decoded = json_decode($models, true);
            $models = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($models)) {
            return [];
        }

        if (! Arr::isAssoc($models)) {
            return array_values(array_unique(array_values(array_filter(array_map(
                static fn (mixed $value) => is_string($value) ? trim($value) : '',
                $models
            )))));
        }

        $normalized = [];

        foreach (['chat', 'embedding', 'image', 'vision'] as $key) {
            $value = Arr::get($models, $key);
            if (is_string($value) && trim($value) !== '') {
                $normalized[$key] = trim($value);
            }
        }

        $available = Arr::get($models, 'available', []);
        if (is_string($available)) {
            $decoded = json_decode($available, true);
            $available = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', $available);
        }

        if (is_array($available)) {
            $normalized['available'] = array_values(array_unique(array_values(array_filter(array_map(
                static fn (mixed $value) => is_string($value) ? trim($value) : '',
                $available
            )))));
        }

        return $normalized;
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->encrypter->decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function normalizeDriver(?string $driver): string
    {
        $normalized = strtolower(trim((string) $driver));

        return self::DRIVER_ALIASES[$normalized] ?? 'openai-compatible';
    }

    public function defaultBaseUrl(?string $driver): string
    {
        return self::DEFAULT_BASE_URLS[$this->normalizeDriver($driver)] ?? self::DEFAULT_BASE_URLS['openai-compatible'];
    }
}
