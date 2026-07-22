<?php
// config/config.php — অ্যাপের কেন্দ্রীয় সেটিংস

// ত্রুটি দেখানো (ডেভেলপমেন্টের সময় সুবিধাজনক; লাইভে false করবে)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// অ্যাপের নাম
define('APP_NAME', 'কনটেন্ট রাইটিং একাডেমি');

// ডেটাবেস ফাইলের পথ (SQLite)
define('DB_PATH', __DIR__ . '/../data/app.sqlite');

// API key এনক্রিপশনের জন্য গোপন চাবি (লাইভে নিজের মতো বদলে নেবে)
define('APP_SECRET', 'change-this-secret-key-2026');

// ---- ফলব্যাক ডিফল্ট AI প্রোভাইডার ও মডেল ----
// ইউজার নিজের সেটিংস না দিলে এগুলো ব্যবহার হবে (তবে key ছাড়া AI চলবে না)
define('DEFAULT_PROVIDER', 'openrouter');
define('DEFAULT_MODEL', 'openai/gpt-4o-mini');

// প্রতিটা প্রোভাইডারের জন্য মডেলের তালিকা (dropdown-এ দেখাবে)
$AI_PROVIDERS = [
    'openrouter' => [
        'label'  => 'OpenRouter',
        'models' => [
            'openai/gpt-4o-mini',
            'openai/gpt-4o',
            'anthropic/claude-3.5-sonnet',
            'google/gemini-flash-1.5',
            'meta-llama/llama-3.1-8b-instruct',
        ],
    ],
    'openai' => [
        'label'  => 'OpenAI',
        'models' => [
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4-turbo',
        ],
    ],
    'anthropic' => [
        'label'  => 'Anthropic (Claude)',
        'models' => [
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
        ],
    ],
];

// টাইমজোন
date_default_timezone_set('Asia/Dhaka');
