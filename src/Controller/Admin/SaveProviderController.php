<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\LlmService;
use Zephyrisle\ZaiBot\Service\ProviderManager;

class SaveProviderController extends AbstractAdminController implements RequestHandlerInterface
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
        $id = Arr::get($request->getQueryParams(), 'id', Arr::get($body, 'id'));
        $provider = $id
            ? $this->providers->update($this->providers->find((int) $id), $body)
            : $this->providers->create($body);

        return $this->ok([
            'data' => $this->providers->serialize($provider),
            'models' => filter_var(Arr::get($body, 'discoverModels', false), FILTER_VALIDATE_BOOL)
                ? $this->llm->discoverModels($provider)
                : null,
        ], $id ? 200 : 201);
    }
}
