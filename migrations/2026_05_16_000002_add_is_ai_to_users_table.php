<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'is_ai')) {
            $schema->table('users', function (Blueprint $table) {
                $table->boolean('is_ai')->default(false);
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'is_ai')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('is_ai');
            });
        }
    },
];
