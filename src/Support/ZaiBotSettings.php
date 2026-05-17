<?php

namespace Zephyrisle\ZaiBot\Support;

final class ZaiBotSettings
{
    public const PREFIX = 'zai-bot.';

    public static function key(string $suffix): string
    {
        return self::PREFIX.$suffix;
    }

    public static function defaults(): array
    {
        return [
            self::key('api_base_url') => 'https://api.openai.com/v1',
            self::key('default_model') => 'gpt-4o-mini',
            self::key('default_temperature') => '0.7',
            self::key('max_output_tokens') => '2000',
            self::key('request_timeout') => '30',
            self::key('database_enabled') => '0',
            self::key('active_posting_enabled') => '0',
            self::key('active_posting_quiet_hours') => '24',
            self::key('active_posting_hourly_limit') => '1',
            self::key('active_posting_daily_limit') => '5',
            self::key('active_posting_probability') => '5',
            self::key('active_posting_max_length') => '800',
            self::key('affection_enabled') => '1',
            self::key('affection_max_positive_delta') => '0.05',
            self::key('affection_max_negative_delta') => '0.10',
            self::key('affection_decay_enabled') => '1',
            self::key('affection_decay_rate') => '0.001',
            self::key('affection_floor') => '0.3',
            self::key('persona_map_enabled') => '1',
            self::key('long_term_memory_enabled') => '0',
            self::key('embedding_model') => 'text-embedding-3-small',
            self::key('memory_retrieval_limit') => '5',
            self::key('memory_initial_strength') => '1.0',
            self::key('memory_decay_rate') => '0.01',
            self::key('memory_cleanup_threshold') => '0.1',
            self::key('short_term_memory_turns') => '10',
            self::key('allow_ai_mentions') => '1',
            self::key('ai_reply_depth_limit') => '3',
            self::key('ai_reply_window_minutes') => '60',
            self::key('ai_reply_window_max') => '10',
            self::key('log_ai_mentions') => '1',
            self::key('cooperation_enabled') => '1',
            self::key('assistant_delay_min') => '5',
            self::key('assistant_delay_max') => '30',
            self::key('master_timeout_seconds') => '60',
            self::key('admin_mode_enabled') => '1',
            self::key('admin_can_view_internal_params') => '1',
            self::key('admin_can_view_reasoning') => '0',
            self::key('log_llm_requests') => '1',
            self::key('verbose_errors') => '0',
            self::key('developer_mode') => '0',
            self::key('tool_like_enabled') => '0',
            self::key('tool_like_cooldown_seconds') => '3600',
            self::key('tool_report_enabled') => '0',
            self::key('tool_report_confirmation_threshold') => '2',
            self::key('tool_follow_enabled') => '0',
            self::key('tool_sensitive_words') => '',
            self::key('provider_catalog') => '[]',
            self::key('auto_discover_models') => '1',
            self::key('allowed_sections') => '',
        ];
    }

    public static function toolDependencies(): array
    {
        return [
            'tool_like_enabled' => ['extensionId' => 'flarum-likes', 'package' => 'flarum/likes'],
            'tool_report_enabled' => ['extensionId' => 'flarum-flags', 'package' => 'flarum/flags'],
            'tool_follow_enabled' => ['extensionId' => 'fof-reactions', 'package' => 'fof/reactions'],
            'fof_upload_enabled' => ['extensionId' => 'fof-upload', 'package' => 'fof/upload'],
        ];
    }
}
