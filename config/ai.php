<?php

declare(strict_types=1);

return [
    'chat_provider' => env('AI_CHAT_PROVIDER', 'openai'),
    'chat_model' => env('AI_CHAT_MODEL', 'gpt-4o'),
    'embedding_provider' => env('AI_EMBEDDING_PROVIDER', 'openai'),
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
    'temperature' => (float) env('AI_TEMPERATURE', 0.7),
    'session_ttl_minutes' => (int) env('AI_SESSION_TTL', 10),
    'session_max_minutes' => (int) env('AI_SESSION_MAX', 120),
    'allowed_roles' => ['sindico', 'administradora', 'condomino'],
];
