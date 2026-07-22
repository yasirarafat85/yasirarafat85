<?php
// includes/auth.php — সেশন, লগইন গার্ড ও ইউজার ফাংশন
// প্রতিটা পেজের শুরুতে এই ফাইলটি require করবে।

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

// এখন লগইন করা ইউজার (না থাকলে null)
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

// লগইন বাধ্যতামূলক — না থাকলে লগইন পেজে পাঠাও
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        set_flash('warning', 'এই পেজ দেখতে আগে লগইন করুন।');
        redirect('auth/login.php');
    }
    return $user;
}

// লগইন করানো
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

// লগআউট
function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}
