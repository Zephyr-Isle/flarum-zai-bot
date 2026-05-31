<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'embedding' => 'array',
        'strength' => 'float',
        'last_accessed_at' => 'datetime',
    ];

    public function scopeNearestTo(Builder $query, array $embedding, int $limit = 5): Builder
    {
        // 对于所有数据库，直接返回最新的记忆，不使用向量搜索
        return $query
            ->select('*')
            ->selectRaw('0 as neighbor_distance')
            ->latest('id')
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
