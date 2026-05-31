<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends AbstractModel
{
    protected $table = 'ai_providers';

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
