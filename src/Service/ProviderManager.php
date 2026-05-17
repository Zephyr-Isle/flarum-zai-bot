<?php

namespace Zephyrisle\ZaiBot\Service;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Arr;
use Zephyrisle\ZaiBot\Model\AiProvider;

class ProviderManager
{
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
        $provider = new AiProvider();

        return $this->save($provider, $attributes);
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
        return [
            'id' => (int) $provider->id,
            'name' => $provider->name,
            'driver' => $provider->driver,
            'baseUrl' => $provider->base_url,
            'models' => $provider->models ?? [],
            'isActive' => (bool) $provider->is_active,
            'apiKeyConfigured' => ! empty($provider->api_key_encrypted),
            'apiKey' => $includeSecret ? $this->decrypt($provider->api_key_encrypted) : null,
            'createdAt' => optional($provider->created_at)?->toAtomString(),
            'updatedAt' => optional($provider->updated_at)?->toAtomString(),
        ];
    }

    private function save(AiProvider $provider, array $attributes): AiProvider
    {
        $models = Arr::get($attributes, 'models', $provider->models ?? []);
        if (is_string($models)) {
            $decoded = json_decode($models, true);
            $models = is_array($decoded) ? $decoded : [];
        }

        $provider->name = Arr::get($attributes, 'name', $provider->name);
        $provider->driver = Arr::get($attributes, 'driver', $provider->driver ?: 'openai-compatible');
        $provider->base_url = rtrim(Arr::get($attributes, 'baseUrl', $provider->base_url ?? 'https://api.openai.com/v1'), '/');
        $provider->models = $models;
        $provider->is_active = filter_var(Arr::get($attributes, 'isActive', $provider->is_active ?? true), FILTER_VALIDATE_BOOL);

        $apiKey = Arr::get($attributes, 'apiKey');
        if (is_string($apiKey) && $apiKey !== '') {
            $provider->api_key_encrypted = $this->encrypter->encryptString($apiKey);
        } elseif (! $provider->exists) {
            $provider->api_key_encrypted = '';
        }

        $provider->save();

        return $provider->refresh();
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
}
