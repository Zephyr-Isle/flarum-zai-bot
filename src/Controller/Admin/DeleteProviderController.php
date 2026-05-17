<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\ProviderManager;

class DeleteProviderController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(private ProviderManager $providers)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        $id = (int) $this->routeParam($request, 'id');
        $this->providers->delete($this->providers->find($id));

        return $this->ok(['deleted' => true]);
    }
}
