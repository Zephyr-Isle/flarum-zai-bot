<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector as VectorCast;
use Pgvector\Vector;

class ConversationMemory extends AbstractModel
{
    protected $table = 'conversation_memories';

    protected $fillable = [
        'user_id',
        'discussion_id',
        'ai_agent_id',
        'summary',
        'embedding',
        'strength',
        'last_accessed_at',
    ];

    protected $casts = [
        'embedding' => VectorCast::class,
        'strength' => 'float',
        'last_accessed_at' => 'datetime',
    ];

    public function scopeNearestTo(Builder $query, array $embedding, int $limit = 5): Builder
    {
        return $query
            ->select('*')
            ->selectRaw('embedding <=> ? as neighbor_distance', [new Vector($embedding)])
            ->orderByRaw('embedding <=> ?', [new Vector($embedding)])
            ->limit($limit);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}
