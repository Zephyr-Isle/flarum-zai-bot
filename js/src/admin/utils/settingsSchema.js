export default function settingsSchema(translator) {
  return [
    {
      key: 'api',
      fields: [
        { key: 'api_base_url', type: 'text' },
        { key: 'api_key', type: 'password' },
        { key: 'default_model', type: 'text' },
        { key: 'default_temperature', type: 'number', min: 0, max: 1, step: 0.1 },
        { key: 'max_output_tokens', type: 'number', min: 1, step: 1 },
        { key: 'request_timeout', type: 'number', min: 1, step: 1 },
        { key: 'provider_catalog', type: 'textarea', rows: 6 },
        { key: 'auto_discover_models', type: 'boolean' },
      ],
    },
    {
      key: 'database',
      fields: [
        { key: 'database_enabled', type: 'boolean' },
        { key: 'database_host', type: 'text' },
        { key: 'database_port', type: 'number', min: 1, step: 1 },
        { key: 'database_name', type: 'text' },
        { key: 'database_username', type: 'text' },
        { key: 'database_password', type: 'password' },
      ],
    },
    {
      key: 'agents',
      fields: [
        { type: 'markup', content: translator('agents.scaffold_note') },
        { key: 'allowed_sections', type: 'textarea', rows: 4 },
      ],
    },
    {
      key: 'tools',
      fields: [
        { key: 'tool_like_enabled', type: 'boolean', dependency: 'flarum-likes' },
        { key: 'tool_like_cooldown_seconds', type: 'number', min: 0, step: 1 },
        { key: 'tool_report_enabled', type: 'boolean', dependency: 'flarum-flags' },
        { key: 'tool_report_confirmation_threshold', type: 'number', min: 1, step: 1 },
        { key: 'tool_follow_enabled', type: 'boolean', dependency: 'fof-reactions' },
        { key: 'tool_sensitive_words', type: 'textarea', rows: 6 },
        { type: 'markup', content: translator('tools.upload_note') },
      ],
    },
    {
      key: 'active_posting',
      fields: [
        { key: 'active_posting_enabled', type: 'boolean' },
        { key: 'active_posting_quiet_hours', type: 'number', min: 1, step: 1 },
        { key: 'active_posting_hourly_limit', type: 'number', min: 0, step: 1 },
        { key: 'active_posting_daily_limit', type: 'number', min: 0, step: 1 },
        { key: 'active_posting_probability', type: 'number', min: 0, max: 100, step: 1 },
        { key: 'active_posting_max_length', type: 'number', min: 1, step: 1 },
      ],
    },
    {
      key: 'memory',
      fields: [
        { key: 'affection_enabled', type: 'boolean' },
        { key: 'affection_max_positive_delta', type: 'number', min: 0, max: 1, step: 0.01 },
        { key: 'affection_max_negative_delta', type: 'number', min: 0, max: 1, step: 0.01 },
        { key: 'affection_decay_enabled', type: 'boolean' },
        { key: 'affection_decay_rate', type: 'number', min: 0, max: 1, step: 0.001 },
        { key: 'affection_floor', type: 'number', min: 0, max: 1, step: 0.01 },
        { key: 'persona_map_enabled', type: 'boolean' },
        { key: 'long_term_memory_enabled', type: 'boolean' },
        { key: 'embedding_model', type: 'text' },
        { key: 'memory_retrieval_limit', type: 'number', min: 1, step: 1 },
        { key: 'memory_initial_strength', type: 'number', min: 0, max: 1, step: 0.1 },
        { key: 'memory_decay_rate', type: 'number', min: 0, max: 1, step: 0.01 },
        { key: 'memory_cleanup_threshold', type: 'number', min: 0, max: 1, step: 0.01 },
        { key: 'short_term_memory_turns', type: 'number', min: 1, step: 1 },
      ],
    },
    {
      key: 'ai_loops',
      fields: [
        { key: 'allow_ai_mentions', type: 'boolean' },
        { key: 'ai_reply_depth_limit', type: 'number', min: 1, step: 1 },
        { key: 'ai_reply_window_minutes', type: 'number', min: 1, step: 1 },
        { key: 'ai_reply_window_max', type: 'number', min: 1, step: 1 },
        { key: 'log_ai_mentions', type: 'boolean' },
      ],
    },
    {
      key: 'cooperation',
      fields: [
        { key: 'cooperation_enabled', type: 'boolean' },
        { key: 'assistant_delay_min', type: 'number', min: 0, step: 1 },
        { key: 'assistant_delay_max', type: 'number', min: 0, step: 1 },
        { key: 'master_timeout_seconds', type: 'number', min: 1, step: 1 },
      ],
    },
    {
      key: 'admin_mode',
      fields: [
        { key: 'admin_mode_enabled', type: 'boolean' },
        { key: 'admin_can_view_internal_params', type: 'boolean' },
        { key: 'admin_can_view_reasoning', type: 'boolean' },
      ],
    },
    {
      key: 'debug',
      fields: [
        { key: 'log_llm_requests', type: 'boolean' },
        { key: 'verbose_errors', type: 'boolean' },
        { key: 'developer_mode', type: 'boolean' },
        { type: 'markup', content: translator('debug.export_note') },
      ],
    },
  ];
}
