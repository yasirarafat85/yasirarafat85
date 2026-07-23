<?php
// providers/openrouter.php — OpenRouter API (OpenAI-সদৃশ ফরম্যাট)
// সাধারণ ইন্টারফেস: provider_openrouter($apiKey, $model, $system, $userPrompt): array

function provider_openrouter(string $apiKey, string $model, string $system, string $userPrompt, float $temperature = 0.7): array
{
    $body = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'temperature' => $temperature,
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        // OpenRouter সুপারিশকৃত (ঐচ্ছিক) হেডার
        'HTTP-Referer: http://localhost',
        'X-Title: Content Writing Academy',
    ];

    [$code, $json, $err] = ai_http_post('https://openrouter.ai/api/v1/chat/completions', $headers, $body);
    if ($err) return ['ok' => false, 'text' => '', 'error' => $err];
    if ($code !== 200) {
        return ['ok' => false, 'text' => '', 'error' => ai_api_error($json, $code)];
    }
    $text = $json['choices'][0]['message']['content'] ?? '';
    return ['ok' => true, 'text' => trim($text), 'error' => ''];
}
