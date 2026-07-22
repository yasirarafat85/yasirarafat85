<?php
// includes/functions.php — সাধারণ হেল্পার ফাংশন (auth-এর উপর নির্ভরশীল নয়)

// আউটপুট escape (XSS ঠেকাতে) — সব ইউজার ডেটা প্রিন্টের আগে এটা ব্যবহার করো
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// অন্য পেজে পাঠানো
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---- Flash message (এক পেজ থেকে আরেক পেজে ছোট বার্তা) ----
function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- CSRF সুরক্ষা ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        die('নিরাপত্তা যাচাই ব্যর্থ (CSRF)। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
    }
}

// শব্দ সংখ্যা গণনা (বাংলা/ইংরেজি — ফাঁকা দিয়ে ভাগ)
function count_words(string $text): int
{
    $text = trim($text);
    if ($text === '') return 0;
    return count(preg_split('/\s+/u', $text));
}

// আনুমানিক পড়ার সময় (মিনিট)
function reading_time(int $wordCount): int
{
    return max(1, (int)ceil($wordCount / 150));
}

// ইনপুট পরিষ্কার করা
function clean(string $s): string
{
    return trim($s);
}

// ---- API key নিরাপদে রাখা (সিম্পল এনক্রিপশন) ----
function encrypt_key(string $plain): string
{
    if ($plain === '') return '';
    $key = hash('sha256', APP_SECRET, true);      // ৩২ বাইট চাবি
    $iv  = random_bytes(16);                        // প্রতিবার নতুন র‍্যান্ডম IV
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);           // IV সাইফারটেক্সটের সাথে জুড়ে রাখা
}

function decrypt_key(string $enc): string
{
    if ($enc === '') return '';
    $key  = hash('sha256', APP_SECRET, true);
    $data = base64_decode($enc, true);
    if ($data === false || strlen($data) < 17) return '';
    $iv     = substr($data, 0, 16);                // শুরুর ১৬ বাইট = IV
    $cipher = substr($data, 16);
    return (string)openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

// key masked করে দেখানো (যেমন sk-...abcd)
function mask_key(string $plain): string
{
    if ($plain === '') return '';
    $len = strlen($plain);
    if ($len <= 8) return str_repeat('•', $len);
    return substr($plain, 0, 4) . str_repeat('•', 6) . substr($plain, -4);
}
