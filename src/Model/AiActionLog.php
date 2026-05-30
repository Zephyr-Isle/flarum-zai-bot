<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Zephyrisle\ZaiBot\Support\DatabaseConfig;

class AiActionLog extends AbstractModel
{
    public $timestamps = false;

    protected $table = 'ai_action_logs';

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
        'ai_agent_id',
        'user_id',
        'action_type',
        'target_type',
        'target_id',
        'result',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'target_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
