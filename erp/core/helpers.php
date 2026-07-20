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

/* ---------------- App Center হেল্পার ---------------- */

/**
 * অ্যাপের সংরক্ষিত path কে পূর্ণ নেটওয়ার্ক পাথে রূপান্তর করে।
 * full path (\\... বা X:\...) হলে যেমন আছে তেমন, নইলে বেস শেয়ারের সাথে জোড়া।
 */
function resolve_app_path(string $stored): string
{
    $stored = trim($stored);
    // ইতিমধ্যে UNC (\\server) বা ড্রাইভ (C:\) হলে সরাসরি ব্যবহার
    if (preg_match('#^(\\\\\\\\|[A-Za-z]:\\\\)#', $stored)) {
        return $stored;
    }
    return rtrim(NETWORK_SHARE_BASE, '\\/') . '\\' . ltrim($stored, '\\/');
}

/** silent-install টোকেন তৈরি (দিন-ভিত্তিক, ফলে নিজে থেকেই মেয়াদ শেষ হয়) */
function app_token(int $appId, ?string $day = null): string
{
    $day = $day ?? date('Y-m-d');
    return substr(hash_hmac('sha256', $appId . '|' . $day, APP_SECRET), 0, 32);
}

/** টোকেন যাচাই — আজ অথবা গতকালের টোকেন গ্রহণযোগ্য (সময়-সীমান্ত এড়াতে) */
function app_token_valid(int $appId, string $token): bool
{
    foreach ([date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))] as $day) {
        if (hash_equals(app_token($appId, $day), $token)) {
            return true;
        }
    }
    return false;
}

/**
 * কলাম না থাকলে যোগ করে (পুরনো ডাটাবেসে নতুন ফিচার আনার জন্য নিরাপদ মাইগ্রেশন)।
 * MySQL/MariaDB দুটোতেই কাজ করে।
 */
function ensure_column(Database $db, string $table, string $column, string $definition): void
{
    $exists = $db->scalar(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
        [$table, $column]
    );
    if (!$exists) {
        $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

/** ক্লায়েন্টের IP নিরাপদে বের করা (প্রক্সি থাকলেও) */
function client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            return trim(explode(',', $_SERVER[$k])[0]);
        }
    }
    return 'unknown';
}
