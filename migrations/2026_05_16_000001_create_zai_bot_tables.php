<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $tables = [
            'ai_session_state',
            'ai_action_logs',
            'conversation_memories',
            'user_ai_memories',
            'ai_agents',
            'ai_providers',
        ];

        foreach ($tables as $table) {
            if ($schema->hasTable($table)) {
                $schema->drop($table);
            }
        }

        $schema->create('ai_providers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('driver')->default('openai-compatible');
            $table->string('base_url');
            $table->text('api_key_encrypted');
            $table->json('models')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('ai_agents', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('flarum_user_id')->unsigned()->unique();
            $table->integer('provider_id')->unsigned()->nullable();
            $table->foreign('flarum_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('ai_providers')->onDelete('set null');
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->text('personality');
            $table->text('expertise')->nullable();
            $table->text('system_prompt')->nullable();
            $table->decimal('temperature', 4, 2)->default(0.7);
            $table->boolean('is_active')->default(true);
            $table->string('reply_mode', 20)->default('mention');
            $table->json('active_tags')->nullable();
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
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('ai_agent_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ai_agent_id')->references('id')->on('ai_agents')->onDelete('cascade');
            $table->float('affection_score')->default(0.5);
            $table->json('personality_tags')->nullable();
            $table->unsignedInteger('interaction_count')->default(0);
            $table->timestamp('last_interaction')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'ai_agent_id']);
        });

        $schema->create('conversation_memories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('discussion_id')->unsigned()->nullable();
            $table->integer('ai_agent_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('discussion_id')->references('id')->on('discussions')->onDelete('set null');
            $table->foreign('ai_agent_id')->references('id')->on('ai_agents')->onDelete('set null');
            $table->text('summary');
            $table->float('strength')->default(1.0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->json('embedding')->nullable();
        });

        $schema->create('ai_action_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('ai_agent_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('ai_agent_id')->references('id')->on('ai_agents')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('action_type', 50);
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('result', 20);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $schema->create('ai_session_state', function (Blueprint $table) {
            $table->string('session_key', 191)->primary();
            $table->json('context');
            $table->json('emotions');
            $table->timestamp('expires_at');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('ai_session_state');
        $schema->dropIfExists('ai_action_logs');
        $schema->dropIfExists('conversation_memories');
        $schema->dropIfExists('user_ai_memories');
        $schema->dropIfExists('ai_agents');
        $schema->dropIfExists('ai_providers');
    },
];
