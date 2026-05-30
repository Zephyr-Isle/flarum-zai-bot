<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zephyrisle\ZaiBot\Support\DatabaseConfig;

class AiAgent extends AbstractModel
{
    protected $table = 'ai_agents';

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
        'flarum_user_id',
        'provider_id',
        'name',
        'avatar_url',
        'personality',
        'expertise',
        'system_prompt',
        'temperature',
        'is_active',
        'reply_mode',
        'active_tags',
        'cooperation_role',
        'hourly_post_limit',
        'daily_post_limit',
        'chat_model',
        'vision_model',
        'embedding_model',
        'language',
    ];

    protected $casts = [
        'active_tags' => 'array',
        'is_active' => 'boolean',
        'temperature' => 'float',
        'hourly_post_limit' => 'integer',
        'daily_post_limit' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(AiActionLog::class, 'ai_agent_id');
    }
}
