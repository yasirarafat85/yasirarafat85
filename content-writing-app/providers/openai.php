<?php
// providers/openai.php — OpenAI Chat Completions API
// সাধারণ ইন্টারফেস: provider_openai($apiKey, $model, $system, $userPrompt): array
// রিটার্ন: ['ok'=>bool, 'text'=>string, 'error'=>string]

function provider_openai(string $apiKey, string $model, string $system, string $userPrompt): array
{
    $body = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'temperature' => 0.7,
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    [$code, $json, $err] = ai_http_post('https://api.openai.com/v1/chat/completions', $headers, $body);
    if ($err) return ['ok' => false, 'text' => '', 'error' => $err];
    if ($code !== 200) {
        return ['ok' => false, 'text' => '', 'error' => ai_api_error($json, $code)];
    }
    $text = $json['choices'][0]['message']['content'] ?? '';
    return ['ok' => true, 'text' => trim($text), 'error' => ''];
}
