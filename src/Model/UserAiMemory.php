<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAiMemory extends AbstractModel
{
    protected $table = 'user_ai_memories';

    protected $fillable = [
        'user_id',
        'ai_agent_id',
        'affection_score',
        'personality_tags',
        'interaction_count',
        'last_interaction',
    ];

    protected $casts = [
        'affection_score' => 'float',
        'personality_tags' => 'array',
        'interaction_count' => 'integer',
        'last_interaction' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}
