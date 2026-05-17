<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        if ($db->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Zai Bot requires PostgreSQL with the pgvector extension.');
        }

        $db->statement('CREATE EXTENSION IF NOT EXISTS vector');

        $schema->create('ai_providers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('driver')->default('openai-compatible');
            $table->string('base_url');
            $table->text('api_key_encrypted');
            $table->jsonb('models')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('ai_agents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('flarum_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->text('personality');
            $table->text('expertise')->nullable();
            $table->text('system_prompt')->nullable();
            $table->decimal('temperature', 4, 2)->default(0.7);
            $table->boolean('is_active')->default(true);
            $table->string('reply_mode', 20)->default('mention');
            $table->jsonb('active_tags')->nullable();
            $table->string('cooperation_role', 20)->default('none');
            $table->unsignedInteger('hourly_post_limit')->nullable();
            $table->unsignedInteger('daily_post_limit')->nullable();
            $table->string('chat_model')->nullable();
            $table->string('vision_model')->nullable();
            $table->string('embedding_model')->nullable();
            $table->string('language')->nullable();
            $table->timestamps();
        });

        $schema->create('user_ai_memories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->cascadeOnDelete();
            $table->float('affection_score')->default(0.5);
            $table->jsonb('personality_tags')->nullable();
            $table->unsignedInteger('interaction_count')->default(0);
            $table->timestampTz('last_interaction')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'ai_agent_id']);
        });

        $schema->create('conversation_memories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('discussion_id')->nullable()->constrained('discussions')->nullOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->text('summary');
            $table->float('strength')->default(1.0);
            $table->timestampTz('last_accessed_at')->nullable();
            $table->timestamps();
        });

        $db->statement('ALTER TABLE conversation_memories ADD COLUMN embedding vector(1536)');
        $db->statement('CREATE INDEX conversation_memories_embedding_hnsw_idx ON conversation_memories USING hnsw (embedding vector_cosine_ops)');

        $schema->create('ai_action_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 50);
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('result', 20);
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        $db->statement(<<<'SQL'
CREATE UNLOGGED TABLE ai_session_state (
    session_key varchar(191) PRIMARY KEY,
    context jsonb NOT NULL DEFAULT '[]'::jsonb,
    emotions jsonb NOT NULL DEFAULT '{}'::jsonb,
    expires_at timestamptz NOT NULL
)
SQL);
    },
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();

        $db->statement('DROP TABLE IF EXISTS ai_session_state');
        $schema->dropIfExists('ai_action_logs');
        $db->statement('DROP INDEX IF EXISTS conversation_memories_embedding_hnsw_idx');
        $schema->dropIfExists('conversation_memories');
        $schema->dropIfExists('user_ai_memories');
        $schema->dropIfExists('ai_agents');
        $schema->dropIfExists('ai_providers');
    },
];
