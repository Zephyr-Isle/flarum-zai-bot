<?php

namespace Zephyrisle\ZaiBot\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use Zephyrisle\ZaiBot\Support\ZaiBotSettings;

class SettingAccessor
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get(ZaiBotSettings::key($key), $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function allDefaultsMerged(): array
    {
        $values = [];

        foreach (ZaiBotSettings::defaults() as $key => $default) {
            $values[$key] = $this->settings->get($key, $default);
        }

        return $values;
    }
}