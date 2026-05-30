<?php

namespace Zephyrisle\ZaiBot\Model;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Zephyrisle\ZaiBot\Support\DatabaseConfig;

class ConversationMemory extends AbstractModel
{
    protected $table = 'conversation_memories';

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
        $driver = $this->getConnection()->getDriverName();

        if ($driver === 'pgsql' && class_exists('Pgvector\Laravel\Vector') && class_exists('Pgvector\Vector')) {
            return $query
                ->select('*')
                ->selectRaw('embedding <=> ? as neighbor_distance', [new \Pgvector\Vector($embedding)])
                ->orderByRaw('embedding <=> ?', [new \Pgvector\Vector($embedding)])
                ->limit($limit);
        }

        // For MySQL and other databases, just return recent memories without vector search
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
