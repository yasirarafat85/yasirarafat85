<?php
// providers/anthropic.php — Anthropic (Claude) Messages API
// ফরম্যাট আলাদা: x-api-key হেডার, system আলাদা ফিল্ড, content অ্যারে
// সাধারণ ইন্টারফেস: provider_anthropic($apiKey, $model, $system, $userPrompt): array

function provider_anthropic(string $apiKey, string $model, string $system, string $userPrompt, float $temperature = 0.7): array
{
    $body = [
        'model' => $model,
        'max_tokens' => 1024,
        'temperature' => $temperature,
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
