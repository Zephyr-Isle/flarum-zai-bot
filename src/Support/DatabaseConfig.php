<?php

namespace Zephyrisle\ZaiBot\Support;

use Flarum\Settings\SettingsRepositoryInterface;

class DatabaseConfig
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function useDedicatedDatabase(): bool
    {
        return filter_var($this->settings->get(ZaiBotSettings::key('database_enabled'), false), FILTER_VALIDATE_BOOL);
    }

    public function connectionSettings(): array
    {
        return [
            'host' => $this->settings->get(ZaiBotSettings::key('database_host'), '127.0.0.1'),
            'port' => (int) $this->settings->get(ZaiBotSettings::key('database_port'), 5432),
            'database' => $this->settings->get(ZaiBotSettings::key('database_name'), ''),
            'username' => $this->settings->get(ZaiBotSettings::key('database_username'), ''),
            'password' => $this->settings->get(ZaiBotSettings::key('database_password'), ''),
        ];
    }

    public function dsn(array $config): string
    {
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 5432),
            $config['database'] ?? ''
        );
    }
}
