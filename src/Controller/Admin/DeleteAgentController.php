<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\AgentManager;

class DeleteAgentController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(private AgentManager $agents)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        $id = (int) $this->routeParam($request, 'id');
        $deleteUser = filter_var($this->routeParam($request, 'deleteUser', false), FILTER_VALIDATE_BOOL);

        $this->agents->delete($this->agents->find($id), $deleteUser);

        return $this->ok(['deleted' => true]);
    }
}
