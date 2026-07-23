<?php
/**
 * ---------------------------------------------------------------
 *  Auth — লগইন, সেশন ও এক্সেস কন্ট্রোল (RBAC)
 * ---------------------------------------------------------------
 *  ব্যবহার:
 *    Auth::attempt($email, $password)   // লগইন চেষ্টা
 *    Auth::check()                       // লগইন করা আছে কিনা
 *    Auth::user()                        // বর্তমান ইউজার (array)
 *    Auth::can('users.create')           // এই permission আছে কিনা
 *    Auth::requireLogin()                // না থাকলে login পেজে পাঠাবে
 *    Auth::requirePermission('users.view')
 *    Auth::logout()
 */
class Auth
{
    /** লগইন চেষ্টা — সফল হলে true */
    public static function attempt(string $email, string $password): bool
    {
        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1",
            [$email]
        );
        if ($user && password_verify($password, $user['password'])) {
            // সেশন হাইজ্যাক ঠেকাতে নতুন session id
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
            self::loadPermissions((int) $user['id']);
            return true;
        }
        return false;
    }

    /** লগইন করা আছে কিনা */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /** বর্তমান ইউজার (role নামসহ) */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        static $cached = null;
        if ($cached === null) {
            $cached = Database::getInstance()->fetch(
                "SELECT u.*, r.name AS role_name
                 FROM users u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ? LIMIT 1",
                [$_SESSION['user_id']]
            );
        }
        return $cached ?: null;
    }

    /** এই ইউজারের সব permission session-এ লোড করা */
    public static function loadPermissions(int $userId): void
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT p.slug
             FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.id = ?",
            [$userId]
        );
        $_SESSION['permissions'] = array_column($rows, 'slug');
    }

    /** নির্দিষ্ট permission আছে কিনা (Super Admin সব পারে) */
    public static function can(string $slug): bool
    {
        if (!self::check()) {
            return false;
        }
        $perms = $_SESSION['permissions'] ?? [];
        return in_array('*', $perms, true) || in_array($slug, $perms, true);
    }

    /** লগইন না থাকলে login পেজে পাঠাও */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('auth/login.php');
        }
        // প্রতি রিকোয়েস্টে permission fresh রাখতে
        if (!isset($_SESSION['permissions'])) {
            self::loadPermissions((int) $_SESSION['user_id']);
        }
    }

    /** permission না থাকলে থামিয়ে দাও */
    public static function requirePermission(string $slug): void
    {
        self::requireLogin();
        if (!self::can($slug)) {
            http_response_code(403);
            include __DIR__ . '/../includes/403.php';
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
