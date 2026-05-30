<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zephyrisle\ZaiBot\Support\DatabaseConfig;

class AiProvider extends AbstractModel
{
    protected $table = 'ai_providers';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        /** @var DatabaseConfig $databaseConfig */
        $databaseConfig = resolve(DatabaseConfig::class);
        
        if ($databaseConfig->useSeparateDatabase()) {
            $this->setConnection($databaseConfig->getConnectionName());
        }
    }

    protected $fillable = [
        'name',
        'driver',
        'base_url',
        'api_key_encrypted',
        'models',
        'is_active',
    ];

    protected $casts = [
        'models' => 'array',
        'is_active' => 'boolean',
    ];

    public function agents(): HasMany
    {
        return $this->hasMany(AiAgent::class, 'provider_id');
    }
}
