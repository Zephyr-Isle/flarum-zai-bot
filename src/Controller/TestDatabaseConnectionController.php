<?php

namespace Zephyrisle\ZaiBot\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zephyrisle\ZaiBot\Support\DatabaseConfig;

class TestDatabaseConnectionController
{
    public function __construct(private DatabaseConfig $databaseConfig)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $payload = $request->getParsedBody() ?? [];
        $config = array_filter($payload) ? $payload : $this->databaseConfig->connectionSettings();

        try {
            $pdo = new PDO(
                $this->databaseConfig->dsn($config),
                $config['username'] ?? '',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );

            $pdo->query('SELECT 1');

            return new JsonResponse([
                'ok' => true,
                'message' => 'Connection established successfully.',
            ]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
