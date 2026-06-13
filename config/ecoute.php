<?php

declare(strict_types=1);

return [
    'enabled' => env('ECOUTE_ENABLED', false),
    'environments' => array_values(array_filter(array_map('trim', explode(',', env('ECOUTE_ENVIRONMENTS', 'local,staging'))))),

    // Keyboard shortcut to toggle the overlay. Format: modifier(s)+key, e.g. ctrl+shift+e
    'shortcut' => env('ECOUTE_SHORTCUT', 'ctrl+shift+e'),

    // Language for AI-generated issue content. Any BCP-47 locale or plain name ('fr', 'en', 'es').
    'locale' => env('ECOUTE_ISSUE_LOCALE', 'en'),

    // Provide a custom closure here to override the default Gate check.
    // Example: 'gate' => fn ($user) => $user->is_admin,
    // Example: 'gate' => fn ($user) => $user->hasRole('admin'),
    'gate' => null,

    'ai' => [
        'provider' => env('ECOUTE_AI_PROVIDER', 'openai'),
        'temperature' => (float) env('ECOUTE_AI_TEMPERATURE', 0.0),

        'openai' => [
            'api_key' => env('ECOUTE_AI_API_KEY'),
            'model' => env('ECOUTE_OPENAI_MODEL', 'gpt-4o'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            // Check https://docs.anthropic.com/en/docs/about-claude/models for current models.
            'model' => env('ECOUTE_ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        ],
    ],

    'queue' => [
        'connection' => env('ECOUTE_QUEUE_CONNECTION', 'default'),
        // Optional: specify a named queue to place jobs onto (e.g. 'ecoute-high')
        'name' => env('ECOUTE_QUEUE_NAME', null),
        'retries' => 3,
        'backoff' => 60, // seconds; job uses exponential backoff
    ],

    'rate_limit' => [
        'attempts' => 10,
        'decay_minutes' => 1,
    ],

    'deduplication' => [
        'enabled' => true,
        'window_hours' => 24,
    ],

    'screenshot' => [
        'enabled' => true,
        'max_width' => 800,
        'quality' => 0.5,
        'max_size_kb' => 512,
        // 'disk'  = store to configured filesystem disk (required for GitHub issue attachments)
        // 'none'  = do not store
        'storage' => env('ECOUTE_SCREENSHOT_STORAGE', 'none'),
        'disk' => env('ECOUTE_SCREENSHOT_DISK', 'public'),
    ],

    'notifications' => [
        'channels' => ['mail'],
        'mail_to' => env('ECOUTE_MAIL_TO'),
    ],

    // URL to view a capture in your admin panel. Use {id} as a placeholder for the capture UUID.
    // Example: 'issue_url' => '/admin/ecoute/{id}',
    'issue_url' => env('ECOUTE_ISSUE_URL'),

    'github' => [
        'enabled' => env('ECOUTE_GITHUB_ENABLED', false),
        'token' => env('ECOUTE_GITHUB_TOKEN'),   // Personal Access Token with repo scope
        'owner' => env('ECOUTE_GITHUB_OWNER'),   // e.g. 'mikehins'
        'repo' => env('ECOUTE_GITHUB_REPO'),    // e.g. 'myapp'
        'labels' => [],                           // Optional: ['bug', 'ecoute']
        // Comma-separated list of allowed template filenames (deny-by-default when set).
        // Example: ECOUTE_GITHUB_TEMPLATE_WHITELIST=bug_report.md,ux_issue.md
        'template_whitelist' => array_filter(array_map('trim', explode(',', env('ECOUTE_GITHUB_TEMPLATE_WHITELIST', '')))),
    ],

    'csp' => [
        'nonce' => false,
    ],

    // Code resolver tuning: caching and limits for scanning host application views.
    'code' => [
        // Source-code extraction is disabled by default because it can send host application
        // snippets to external AI providers. Opt in explicitly if you want this behaviour.
        'enabled' => env('ECOUTE_CODE_ENABLED', false),
        // Seconds to cache resolved source snippets for a given capture/deduplication hash.
        'cache_ttl' => env('ECOUTE_CODE_CACHE_TTL', 3600),
        // Maximum number of files to include (defaults to CodeResolver::MAX_FILES)
        'max_files' => env('ECOUTE_CODE_MAX_FILES', 3),
        // Maximum number of grep results (used internally by the resolver)
        'grep_max' => env('ECOUTE_CODE_GREP_MAX', 2),
    ],
];
