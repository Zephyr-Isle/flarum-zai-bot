<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Model\AiProvider;
use Zephyrisle\ZaiBot\Service\LlmService;
use Zephyrisle\ZaiBot\Service\ProviderManager;

class TestProviderController extends AbstractAdminController implements RequestHandlerInterface
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

        return $this->ok($this->llm->testProvider($provider));
    }

    private function resolveProviderDraft(array $body): AiProvider
    {
        $id = Arr::get($body, 'id');
        $source = $id ? $this->providers->find((int) $id) : null;

        return $this->providers->makeDraft($body, $source);
    }
}
