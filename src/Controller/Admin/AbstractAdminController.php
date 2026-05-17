<?php

namespace Zephyrisle\ZaiBot\Controller\Admin;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractAdminController
{
    protected function assertAdmin(ServerRequestInterface $request): void
    {
        RequestUtil::getActor($request)->assertAdmin();
    }

    protected function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    protected function routeParam(ServerRequestInterface $request, string $key, mixed $default = null): mixed
    {
        return Arr::get($request->getQueryParams(), $key, $default);
    }

    protected function ok(array $payload = [], int $status = 200): ResponseInterface
    {
        return new JsonResponse($payload, $status);
    }
}
