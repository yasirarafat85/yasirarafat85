<?php
/**
 * ---------------------------------------------------------------
 *  helpers — সব জায়গায় কাজে লাগে এমন ছোট ফাংশন
 * ---------------------------------------------------------------
 */

/** HTML escape (XSS ঠেকাতে) — আউটপুটে সবসময় ব্যবহার করুন */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** BASE_URL সহ পূর্ণ লিংক বানায় */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** অন্য পেজে রিডাইরেক্ট */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** এক-বারের বার্তা (flash message) সেট করা */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/** flash বার্তা দেখিয়ে মুছে ফেলা */
function get_flash(string $type): ?string
{
    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/** CSRF টোকেন তৈরি/ফেরত */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** ফর্মে বসানোর জন্য hidden input */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/** POST রিকোয়েস্টে CSRF যাচাই */
function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(419);
            die('নিরাপত্তা টোকেন মেলেনি (CSRF)। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
    }
}

/** GET/POST ভ্যালু নিরাপদে নেওয়া */
function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/** বর্তমান পেজের ফাইলনাম (menu active হাইলাইট করতে) */
function current_page(): string
{
    return basename($_SERVER['SCRIPT_NAME']);
}
