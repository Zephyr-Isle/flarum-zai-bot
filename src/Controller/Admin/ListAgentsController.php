<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\AgentManager;

class ListAgentsController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(private AgentManager $agents)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        return $this->ok([
            'data' => $this->agents->list(),
        ]);
    }
}
