<?php

namespace Zephyrisle\ZaiBot\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Zephyrisle\ZaiBot\Exception\RemoteApiException;
use Zephyrisle\ZaiBot\Model\AiProvider;

class LlmService
{
    public function __construct(
        private ProviderManager $providers,
        private SettingAccessor $settings,
        private Client $http,
        private LoggerInterface $logger
    ) {
    }

    public function discoverModels(?AiProvider $provider = null): array
    {
        $provider = $provider ?: $this->providers->firstActive();

        if ($provider === null) {
            return array_values(array_filter([$this->settings->get('default_model', 'gpt-4o-mini')]));
        }

        try {
            $payload = $this->request($provider, 'GET', '/models');
            $models = collect(Arr::get($payload, 'data', []))
                ->map(fn (array $item) => Arr::get($item, 'id'))
                ->filter(fn ($value) => is_string($value) && $value !== '')
                ->values()
                ->all();

            if ($models !== []) {
                return $models;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('zai-bot model discovery failed', [
                'provider_id' => $provider->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        $fallback = $provider->models ?? [];
        if (is_array($fallback) && Arr::isAssoc($fallback)) {
            $fallback = array_values(array_filter($fallback));
        }

        return array_values(array_unique(array_filter([
            ...((array) $fallback),
            $this->settings->get('default_model', 'gpt-4o-mini'),
        ])));
    }

    public function chat(?AiProvider $provider, array $messages, array $options = []): array
    {
        $provider = $provider ?: $this->providers->firstActive();
        $model = $this->resolveChatModel($provider, $options);

        if ($provider === null) {
            return $this->fallbackChat($messages, $model, 'no_provider');
        }

        try {
            $payload = [
                'model' => $model,
                'messages' => array_values(array_map(
                    static fn (array $message) => [
                        'role' => Arr::get($message, 'role', 'user'),
                        'content' => (string) Arr::get($message, 'content', ''),
                    ],
                    $messages
                )),
                'temperature' => (float) Arr::get($options, 'temperature', $this->settings->float('default_temperature', 0.7)),
                'max_tokens' => (int) Arr::get($options, 'maxTokens', $this->settings->int('max_output_tokens', 2000)),
            ];

            if (Arr::get($options, 'responseFormat') === 'json') {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $result = $this->request($provider, 'POST', '/chat/completions', $payload);
            $message = Arr::get($result, 'choices.0.message', []);
            $content = $this->normalizeMessageContent(Arr::get($message, 'content'));

            if ($content === '') {
                throw new RemoteApiException('Empty content returned by remote chat provider.');
            }

            return [
                'content' => $content,
                'model' => Arr::get($result, 'model', $model),
                'provider' => $this->providers->serialize($provider),
                'usage' => Arr::get($result, 'usage', []),
                'fallback' => false,
                'raw' => $result,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('zai-bot chat request failed', [
                'provider_id' => $provider->id,
                'model' => $model,
                'exception' => $exception->getMessage(),
            ]);

            return $this->fallbackChat($messages, $model, $exception->getMessage(), $provider);
        }
    }

    public function embed(?AiProvider $provider, string $input, ?string $model = null): array
    {
        $provider = $provider ?: $this->providers->firstActive();
        $model = $model ?: $this->resolveEmbeddingModel($provider);

        if ($provider === null || trim($input) === '') {
            return $this->pseudoEmbedding($input);
        }

        try {
            $result = $this->request($provider, 'POST', '/embeddings', [
                'model' => $model,
                'input' => $input,
            ]);

            $embedding = Arr::get($result, 'data.0.embedding');
            if (! is_array($embedding) || $embedding === []) {
                throw new RemoteApiException('Invalid embedding payload.');
            }

            return array_map(static fn ($value) => (float) $value, $embedding);
        } catch (\Throwable $exception) {
            $this->logger->warning('zai-bot embedding request failed', [
                'provider_id' => $provider->id,
                'model' => $model,
                'exception' => $exception->getMessage(),
            ]);

            return $this->pseudoEmbedding($input);
        }
    }

    public function pseudoEmbedding(string $input, int $dimensions = 1536): array
    {
        $vector = array_fill(0, $dimensions, 0.0);

        foreach (preg_split('/\s+/u', mb_strtolower($input)) ?: [] as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            $hash = hash('sha256', $token);
            for ($i = 0; $i < 8; $i++) {
                $offset = hexdec(substr($hash, $i * 4, 4));
                $index = $offset % $dimensions;
                $vector[$index] += (($offset % 2000) / 1000) - 1;
            }
        }

        $norm = sqrt(array_reduce($vector, static fn (float $carry, float $value) => $carry + ($value * $value), 0.0));
        if ($norm > 0.0) {
            foreach ($vector as $index => $value) {
                $vector[$index] = $value / $norm;
            }
        }

        return $vector;
    }

    private function resolveChatModel(?AiProvider $provider, array $options): string
    {
        return (string) (Arr::get($options, 'model')
            ?: Arr::get($provider?->models ?? [], 'chat')
            ?: Arr::get($provider?->models ?? [], 0)
            ?: $this->settings->get('default_model', 'gpt-4o-mini'));
    }

    private function resolveEmbeddingModel(?AiProvider $provider): string
    {
        return (string) (Arr::get($provider?->models ?? [], 'embedding')
            ?: $this->settings->get('embedding_model', 'text-embedding-3-small'));
    }

    private function fallbackChat(array $messages, string $model, string $reason, ?AiProvider $provider = null): array
    {
        $lastUser = collect($messages)->reverse()->first(
            static fn (array $message) => Arr::get($message, 'role') === 'user'
        );

        $prompt = trim((string) Arr::get($lastUser, 'content', ''));
        $reply = $prompt !== ''
            ? '我已收到你的消息：'.$prompt.'。当前远程模型暂不可用，因此先返回本地兜底回复。'
            : '当前远程模型暂不可用，因此先返回本地兜底回复。';

        return [
            'content' => $reply,
            'model' => $model,
            'provider' => $provider ? $this->providers->serialize($provider) : null,
            'usage' => [],
            'fallback' => true,
            'reason' => $reason,
            'raw' => null,
        ];
    }

    private function request(AiProvider $provider, string $method, string $path, array $payload = []): array
    {
        $apiKey = $this->providers->decrypt($provider->api_key_encrypted)
            ?: $this->settings->get('api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RemoteApiException('No API key configured for provider.');
        }

        $url = rtrim($provider->base_url ?: (string) $this->settings->get('api_base_url', ''), '/').'/'.ltrim($path, '/');

        try {
            $response = $this->http->request($method, $url, [
                'timeout' => $this->settings->int('request_timeout', 30),
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload === [] ? null : $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new RemoteApiException($exception->getMessage(), 0, $exception);
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (! is_array($decoded)) {
            throw new RemoteApiException('Remote API did not return valid JSON.');
        }

        if (($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) && ! Arr::has($decoded, 'data')) {
            throw new RemoteApiException((string) Arr::get($decoded, 'error.message', 'Remote API request failed.'));
        }

        return $decoded;
    }

    private function normalizeMessageContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        return trim(collect($content)->map(function (mixed $part) {
            if (is_string($part)) {
                return $part;
            }

            if (is_array($part)) {
                return (string) (Arr::get($part, 'text') ?: Arr::get($part, 'content') ?: '');
            }

            return '';
        })->implode("\n"));
    }
}
