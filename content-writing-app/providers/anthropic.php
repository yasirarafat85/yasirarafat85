<?php
// providers/anthropic.php — Anthropic (Claude) Messages API
// ফরম্যাট আলাদা: x-api-key হেডার, system আলাদা ফিল্ড, content অ্যারে
// সাধারণ ইন্টারফেস: provider_anthropic($apiKey, $model, $system, $userPrompt): array

function provider_anthropic(string $apiKey, string $model, string $system, string $userPrompt, float $temperature = 0.7): array
{
    // দ্রষ্টব্য: নতুন Claude মডেলে (Opus 4.8, Sonnet 5, Haiku 4.5) `temperature`
    // ডিপ্রিকেটেড — পাঠালে 400 এরর দেয়। তাই এখানে পাঠানো হয় না।
    // বৈচিত্র্য আসে system prompt-এর র‍্যান্ডম হুক/টোন থেকে।
    $body = [
        'model' => $model,
        'max_tokens' => 1024,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ];
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ];

    [$code, $json, $err] = ai_http_post('https://api.anthropic.com/v1/messages', $headers, $body);
    if ($err) return ['ok' => false, 'text' => '', 'error' => $err];
    if ($code !== 200) {
        return ['ok' => false, 'text' => '', 'error' => ai_api_error($json, $code)];
    }
    // Claude রেসপন্স: content অ্যারের প্রথম text ব্লক
    $text = $json['content'][0]['text'] ?? '';
    return ['ok' => true, 'text' => trim($text), 'error' => ''];
}
