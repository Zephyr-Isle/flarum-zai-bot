<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zephyrisle\ZaiBot\Service\ProviderManager;

class ListProvidersController extends AbstractAdminController implements RequestHandlerInterface
{
    public function __construct(private ProviderManager $providers)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAdmin($request);

        return $this->ok([
            'data' => $this->providers->list(),
        ]);
    }
}
