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
            return $this->fallbackModelList(null);
        }

        try {
            $models = $this->probeProvider($provider)['models'];

            if ($models !== []) {
                return $models;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('zai-bot model discovery failed', [
                'provider_id' => $provider->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->fallbackModelList($provider);
    }

    public function testProvider(?AiProvider $provider = null): array
    {
        $provider = $provider ?: $this->providers->firstActive();

        if ($provider === null) {
            throw new RemoteApiException('No provider configured for testing.');
        }

        $models = $this->probeProvider($provider)['models'];

        return [
            'success' => true,
            'message' => 'Provider connection succeeded.',
            'driver' => $this->providers->normalizeDriver($provider->driver),
            'baseUrl' => $provider->base_url,
            'modelCount' => count($models),
            'models' => $models,
        ];
    }

    public function chat(?AiProvider $provider, array $messages, array $options = []): array
    {
        $provider = $provider ?: $this->providers->firstActive();
        $model = $this->resolveChatModel($provider, $options);

        if ($provider === null) {
            return $this->fallbackChat($messages, $model, 'no_provider');
        }

        try {
            $result = $this->request(
                $provider,
                'POST',
                $this->chatPath($provider, $model),
                $this->chatPayload($provider, $messages, $model, $options)
            );
            $content = $this->extractChatContent($provider, $result);

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

        if ($provider === null || trim($input) === '' || ! $this->supportsEmbeddings($provider)) {
            return $this->pseudoEmbedding($input);
        }

        try {
            $result = $this->request(
                $provider,
                'POST',
                $this->embeddingPath($provider, $model),
                $this->embeddingPayload($provider, $input, $model)
            );

            $embedding = $this->extractEmbedding($provider, $result);
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
        $available = $this->providerModelList($provider);

        return (string) (Arr::get($options, 'model')
            ?: Arr::get($this->providerModelConfig($provider), 'chat')
            ?: Arr::get($available, 0)
            ?: $this->settings->get('default_model', 'gpt-4o-mini'));
    }

    private function resolveEmbeddingModel(?AiProvider $provider): string
    {
        $default = $this->defaultEmbeddingModel($provider);

        return (string) (Arr::get($this->providerModelConfig($provider), 'embedding') ?: $default);
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
        $driver = $this->driver($provider);
        $apiKey = $this->providers->decrypt($provider->api_key_encrypted)
            ?: $this->settings->get('api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RemoteApiException('No API key configured for provider.');
        }

        $url = $this->buildUrl($provider, $path);

        try {
            $response = $this->http->request($method, $url, [
                'timeout' => $this->settings->int('request_timeout', 30),
                'http_errors' => false,
                'headers' => $this->requestHeaders($driver, $apiKey),
                'json' => $payload === [] ? null : $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new RemoteApiException($exception->getMessage(), 0, $exception);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RemoteApiException(
                (string) (
                    Arr::get($decoded, 'error.message')
                    ?: Arr::get($decoded, 'error.details')
                    ?: Arr::get($decoded, 'message')
                    ?: trim($body)
                    ?: 'Remote API request failed.'
                )
            );
        }

        if (! is_array($decoded)) {
            throw new RemoteApiException('Remote API did not return valid JSON.');
        }

        return $decoded;
    }

    private function driver(?AiProvider $provider): string
    {
        return $this->providers->normalizeDriver($provider?->driver);
    }

    private function modelDiscoveryPath(AiProvider $provider): string
    {
        return $this->driver($provider) === 'google' ? '/models?pageSize=1000' : '/models';
    }

    private function chatPath(AiProvider $provider, string $model): string
    {
        return match ($this->driver($provider)) {
            'anthropic' => '/messages',
            'google' => '/models/'.$this->normalizeGoogleModel($model).':generateContent',
            default => '/chat/completions',
        };
    }

    private function embeddingPath(AiProvider $provider, string $model): string
    {
        return match ($this->driver($provider)) {
            'google' => '/models/'.$this->normalizeGoogleModel($model).':embedContent',
            default => '/embeddings',
        };
    }

    private function chatPayload(AiProvider $provider, array $messages, string $model, array $options): array
    {
        $temperature = (float) Arr::get($options, 'temperature', $this->settings->float('default_temperature', 0.7));
        $maxTokens = (int) Arr::get($options, 'maxTokens', $this->settings->int('max_output_tokens', 2000));
        $system = $this->extractSystemMessages($messages);
        $conversation = $this->normalizeConversationMessages($messages);

        return match ($this->driver($provider)) {
            'anthropic' => array_filter([
                'model' => $model,
                'system' => $system !== '' ? $system : null,
                'messages' => $conversation,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ], static fn ($value) => $value !== null),
            'google' => $this->googleChatPayload($conversation, $system, $temperature, $maxTokens, Arr::get($options, 'responseFormat')),
            default => $this->openAiChatPayload($messages, $model, $temperature, $maxTokens, Arr::get($options, 'responseFormat')),
        };
    }

    private function openAiChatPayload(array $messages, string $model, float $temperature, int $maxTokens, mixed $responseFormat): array
    {
        $payload = [
            'model' => $model,
            'messages' => array_values(array_map(
                static fn (array $message) => [
                    'role' => Arr::get($message, 'role', 'user'),
                    'content' => (string) Arr::get($message, 'content', ''),
                ],
                $messages
            )),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($responseFormat === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    private function googleChatPayload(array $messages, string $system, float $temperature, int $maxTokens, mixed $responseFormat): array
    {
        $payload = [
            'contents' => array_values(array_map(function (array $message) {
                $role = Arr::get($message, 'role', 'user') === 'assistant' ? 'model' : 'user';

                return [
                    'role' => $role,
                    'parts' => [
                        ['text' => (string) Arr::get($message, 'content', '')],
                    ],
                ];
            }, $messages)),
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if ($system !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $system],
                ],
            ];
        }

        if ($responseFormat === 'json') {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        return $payload;
    }

    private function embeddingPayload(AiProvider $provider, string $input, string $model): array
    {
        return match ($this->driver($provider)) {
            'google' => [
                'content' => [
                    'parts' => [
                        ['text' => $input],
                    ],
                ],
                'taskType' => 'SEMANTIC_SIMILARITY',
            ],
            default => [
                'model' => $model,
                'input' => $input,
            ],
        };
    }

    private function extractChatContent(AiProvider $provider, array $result): string
    {
        return match ($this->driver($provider)) {
            'anthropic' => $this->normalizeMessageContent(Arr::get($result, 'content', [])),
            'google' => $this->normalizeMessageContent(Arr::get($result, 'candidates.0.content.parts', [])),
            default => $this->normalizeMessageContent(Arr::get($result, 'choices.0.message.content')),
        };
    }

    private function extractEmbedding(AiProvider $provider, array $result): ?array
    {
        return match ($this->driver($provider)) {
            'google' => Arr::get($result, 'embedding.values'),
            default => Arr::get($result, 'data.0.embedding'),
        };
    }

    private function extractModelList(AiProvider $provider, array $payload): array
    {
        return match ($this->driver($provider)) {
            'google' => collect(Arr::get($payload, 'models', []))
                ->map(fn (array $item) => $this->normalizeGoogleModel((string) Arr::get($item, 'name', '')))
                ->filter()
                ->values()
                ->all(),
            default => collect(Arr::get($payload, 'data', []))
                ->map(fn (array $item) => Arr::get($item, 'id'))
                ->filter()
                ->values()
                ->all(),
        };
    }

    private function requestHeaders(string $driver, string $apiKey): array
    {
        return match ($driver) {
            'anthropic' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'google' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ],
            default => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
        };
    }

    private function buildUrl(AiProvider $provider, string $path): string
    {
        $baseUrl = rtrim($provider->base_url ?: (string) $this->settings->get('api_base_url', ''), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function extractSystemMessages(array $messages): string
    {
        return collect($messages)
            ->filter(static fn (array $message) => Arr::get($message, 'role') === 'system')
            ->map(static fn (array $message) => trim((string) Arr::get($message, 'content', '')))
            ->filter()
            ->implode("\n\n");
    }

    private function normalizeConversationMessages(array $messages): array
    {
        return array_values(array_map(
            static fn (array $message) => [
                'role' => Arr::get($message, 'role', 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => (string) Arr::get($message, 'content', ''),
            ],
            array_values(array_filter(
                $messages,
                static fn (array $message) => Arr::get($message, 'role') !== 'system'
            ))
        ));
    }

    private function supportsEmbeddings(AiProvider $provider): bool
    {
        return $this->driver($provider) !== 'anthropic';
    }

    private function defaultEmbeddingModel(?AiProvider $provider): string
    {
        return match ($this->driver($provider)) {
            'google' => 'text-embedding-004',
            default => (string) $this->settings->get('embedding_model', 'text-embedding-3-small'),
        };
    }

    private function probeProvider(AiProvider $provider): array
    {
        $payload = $this->request($provider, 'GET', $this->modelDiscoveryPath($provider));
        $models = collect($this->extractModelList($provider, $payload))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'payload' => $payload,
            'models' => $models,
        ];
    }

    private function fallbackModelList(?AiProvider $provider): array
    {
        $models = [
            ...$this->providerModelList($provider),
            $this->settings->get('default_model', 'gpt-4o-mini'),
        ];

        return array_values(array_unique(array_values(array_filter(array_map(
            static fn (mixed $value) => is_string($value) ? trim($value) : '',
            $models
        )))));
    }

    private function providerModelConfig(?AiProvider $provider): array
    {
        return is_array($provider?->models) ? $provider->models : [];
    }

    private function providerModelList(?AiProvider $provider): array
    {
        $models = $this->providerModelConfig($provider);

        if ($models === []) {
            return [];
        }

        if (! Arr::isAssoc($models)) {
            return array_values(array_filter($models, static fn (mixed $value) => is_string($value) && trim($value) !== ''));
        }

        $available = Arr::get($models, 'available', []);
        if (! is_array($available)) {
            $available = [];
        }

        return array_values(array_unique(array_values(array_filter([
            ...array_values(array_filter($available, static fn (mixed $value) => is_string($value) && trim($value) !== '')),
            Arr::get($models, 'chat'),
            Arr::get($models, 'embedding'),
            Arr::get($models, 'image'),
            Arr::get($models, 'vision'),
        ], static fn (mixed $value) => is_string($value) && trim($value) !== ''))));
    }

    private function normalizeGoogleModel(string $model): string
    {
        return preg_replace('/^models\//', '', trim($model)) ?: '';
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
