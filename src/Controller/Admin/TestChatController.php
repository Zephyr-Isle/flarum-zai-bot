<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\AgentManager;
use Zephyrisle\ZaiBot\Service\AiResponder;
use Zephyrisle\ZaiBot\Service\ProviderManager;

class TestChatController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(
        private AgentManager $agents,
        private ProviderManager $providers,
        private AiResponder $responder
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        $body = $this->body($request);
        $agentId = (int) Arr::get($body, 'agentId');
        $message = trim((string) Arr::get($body, 'message', ''));

        $agent = $this->agents->find($agentId);
        $agent->setRelation('provider', $agent->provider_id ? $this->providers->find((int) $agent->provider_id) : null);

        $preview = $this->responder->generatePreview($agent, $message, [
            'sourceUser' => $request->getAttribute('actor'),
        ]);

        return $this->ok([
            'data' => $preview,
        ]);
    }
}
