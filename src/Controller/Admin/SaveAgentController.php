<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\AgentManager;

class SaveAgentController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(private AgentManager $agents)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        $body = $this->body($request);
        $id = Arr::get($request->getQueryParams(), 'id', Arr::get($body, 'id'));
        $agent = $id
            ? $this->agents->update($this->agents->find((int) $id), $body)
            : $this->agents->create($body);

        return $this->ok([
            'data' => $this->agents->serialize($agent),
        ], $id ? 200 : 201);
    }
}
