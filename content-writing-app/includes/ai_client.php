<?php
// includes/ai_client.php — কেন্দ্রীয় মাল্টি-প্রোভাইডার AI ক্লায়েন্ট
// বাকি অ্যাপ শুধু ai_chat() ডাকবে — কোন প্রোভাইডার, সেটা এখানে সামলানো হয়।

require_once __DIR__ . '/db.php';

// ---- সাধারণ HTTP POST (cURL, JSON) — সব প্রোভাইডার এটি ব্যবহার করে ----
// রিটার্ন: [httpCode, decodedJsonArray|null, errorString]
function ai_http_post(string $url, array $headers, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [0, null, 'নেটওয়ার্ক সমস্যা: ' . $cerr];
    }
    $json = json_decode($raw, true);
    return [$code, $json, ''];
}

// প্রোভাইডারের ত্রুটি বার্তা সুন্দর করে বের করা
function ai_api_error(?array $json, int $code): string
{
    $msg = $json['error']['message'] ?? ($json['message'] ?? 'অজানা ত্রুটি');
    if ($code === 401 || $code === 403) {
        return 'API key ভুল বা অনুমতি নেই (' . $code . ')। সেটিংসে key যাচাই করুন।';
    }
    if ($code === 429) {
        return 'অনেক বেশি রিকোয়েস্ট বা ক্রেডিট শেষ (429)।';
    }
    return 'AI ত্রুটি (' . $code . '): ' . $msg;
}

// ইউজারের AI সেটিংস (key ডিক্রিপ্ট করা অবস্থায়)
function get_ai_settings(int $userId): array
{
    $st = db()->prepare('SELECT * FROM settings WHERE user_id = ?');
    $st->execute([$userId]);
    $row = $st->fetch() ?: [];

    return [
        'provider'      => $row['provider']      ?? DEFAULT_PROVIDER,
        'api_key'       => isset($row['api_key']) ? decrypt_key($row['api_key']) : '',
        'default_model' => $row['default_model']  ?? DEFAULT_MODEL,
    ];
}

// AI ব্যবহারযোগ্য কিনা (key সেট আছে কিনা)
function ai_available(int $userId): bool
{
    $s = get_ai_settings($userId);
    return $s['api_key'] !== '';
}

// ---- মূল ফাংশন: যেকোনো পেজ এটাই ডাকবে ----
// রিটার্ন: ['ok'=>bool, 'text'=>string, 'error'=>string]
function ai_chat(int $userId, string $system, string $userPrompt, ?string $modelOverride = null): array
{
    $s = get_ai_settings($userId);
    if ($s['api_key'] === '') {
        return ['ok' => false, 'text' => '', 'error' => 'AI সেটআপ করা নেই। সেটিংসে গিয়ে API key দিন।'];
    }

    $provider = $s['provider'];
    $model    = $modelOverride ?: $s['default_model'];

    // প্রোভাইডার অনুযায়ী সঠিক ফাইল ও ফাংশন লোড করা (extensible)
    $map = [
        'openrouter' => 'provider_openrouter',
        'openai'     => 'provider_openai',
        'anthropic'  => 'provider_anthropic',
    ];
    if (!isset($map[$provider])) {
        return ['ok' => false, 'text' => '', 'error' => 'অজানা প্রোভাইডার: ' . $provider];
    }

    require_once __DIR__ . '/../providers/' . $provider . '.php';
    $fn = $map[$provider];
    return $fn($s['api_key'], $model, $system, $userPrompt);
}
