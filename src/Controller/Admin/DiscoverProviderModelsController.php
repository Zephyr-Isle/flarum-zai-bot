<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Model\AiProvider;
use Zephyrisle\ZaiBot\Service\LlmService;
use Zephyrisle\ZaiBot\Service\ProviderManager;

class DiscoverProviderModelsController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(
        private ProviderManager $providers,
        private LlmService $llm
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        $body = $this->body($request);
        $provider = $this->resolveProviderDraft($body);
        $models = $this->llm->discoverModels($provider);

        return $this->ok([
            'models' => $models,
            'modelCount' => count($models),
        ]);
    }

    private function resolveProviderDraft(array $body): AiProvider
    {
        $id = Arr::get($body, 'id');
        $source = $id ? $this->providers->find((int) $id) : null;

        return $this->providers->makeDraft($body, $source);
    }
}
