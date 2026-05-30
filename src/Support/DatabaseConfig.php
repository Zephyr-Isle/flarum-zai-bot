<?php

namespace Zephyrisle\ZaiBot\Support;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseConfig
{
    private SettingsRepositoryInterface $settings;
    private Capsule $capsule;
    private bool $separateDatabaseEnabled;
    private ?string $connectionName;

    public function __construct(SettingsRepositoryInterface $settings, Capsule $capsule)
    {
        $this->settings = $settings;
        $this->capsule = $capsule;
        $this->separateDatabaseEnabled = (bool)$this->settings->get(ZaiBotSettings::key('use_separate_database'), false);
        $this->connectionName = $this->separateDatabaseEnabled 
            ? $this->settings->get(ZaiBotSettings::key('separate_db_connection'), 'zai_bot') 
            : null;

        if ($this->separateDatabaseEnabled) {
            $this->configureSeparateDatabase();
        }
    }

    private function configureSeparateDatabase(): void
    {
        $config = [
            'driver' => 'pgsql',
            'host' => $this->settings->get(ZaiBotSettings::key('separate_db_host'), '127.0.0.1'),
            'port' => $this->settings->get(ZaiBotSettings::key('separate_db_port'), '5432'),
            'database' => $this->settings->get(ZaiBotSettings::key('separate_db_database'), 'zai_bot'),
            'username' => $this->settings->get(ZaiBotSettings::key('separate_db_username'), 'postgres'),
            'password' => $this->settings->get(ZaiBotSettings::key('separate_db_password'), ''),
            'charset' => 'utf8',
            'prefix' => $this->settings->get(ZaiBotSettings::key('separate_db_prefix'), ''),
            'schema' => 'public',
            'sslmode' => $this->settings->get(ZaiBotSettings::key('separate_db_sslmode'), 'prefer'),
        ];

        $this->capsule->addConnection($config, $this->connectionName);
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public function useSeparateDatabase(): bool
    {
        return $this->separateDatabaseEnabled;
    }

    public function getConnection()
    {
        return $this->capsule->connection($this->connectionName);
    }
}
