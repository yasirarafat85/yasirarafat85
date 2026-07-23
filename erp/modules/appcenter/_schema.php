<?php
/**
 * _schema.php — App Center-এর টেবিল ও কলাম নিশ্চিত করে।
 * প্রতিটি appcenter পেজ শুরুতে এটি ডাকে, তাই আলাদা মাইগ্রেশন লাগে না
 * এবং পুরনো ডাটাবেসেও নতুন কলাম/টেবিল নিজে থেকেই যোগ হয়।
 */
function appcenter_ensure_schema(Database $db): void
{
    // ---- apps (সফটওয়্যার তালিকা) ----
    $db->query("CREATE TABLE IF NOT EXISTS apps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        category VARCHAR(60) DEFAULT 'General',
        icon VARCHAR(60) NOT NULL DEFAULT 'bi-app',
        color VARCHAR(20) NOT NULL DEFAULT 'blue',
        install_type VARCHAR(20) NOT NULL DEFAULT 'network',
        network_path VARCHAR(500) DEFAULT NULL,
        winget_id VARCHAR(150) DEFAULT NULL,
        silent_args VARCHAR(255) DEFAULT NULL,
        version VARCHAR(40) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // পুরনো টেবিলে নতুন কলাম যোগ (আগে বানানো ডাটাবেসের জন্য)
    ensure_column($db, 'apps', 'install_type', "VARCHAR(20) NOT NULL DEFAULT 'network'");
    ensure_column($db, 'apps', 'winget_id',    "VARCHAR(150) DEFAULT NULL");
    // per-app guest permission: NULL = গ্লোবাল সেটিং, 'none' = কিছুই না,
    // নইলে comma-list (যেমন 'install,download')
    ensure_column($db, 'apps', 'guest_actions', "VARCHAR(120) DEFAULT NULL");

    // ---- install_logs (কোন পিসি থেকে কোন অ্যাপ ইনস্টল হলো) ----
    $db->query("CREATE TABLE IF NOT EXISTS install_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_id INT DEFAULT NULL,
        app_name VARCHAR(120) DEFAULT NULL,
        method VARCHAR(20) NOT NULL DEFAULT 'silent',   -- silent | download
        status VARCHAR(20) NOT NULL DEFAULT 'started',  -- started | success | failed
        pc_name VARCHAR(120) DEFAULT NULL,
        user_id INT DEFAULT NULL,
        user_name VARCHAR(120) DEFAULT NULL,
        ip_address VARCHAR(60) DEFAULT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ---- settings (key-value; শেয়ার পাথ ইত্যাদি Settings থেকে বদলানো যায়) ----
    $db->query("CREATE TABLE IF NOT EXISTS settings (
        skey   VARCHAR(80) PRIMARY KEY,
        svalue TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** একটি সেটিং পড়া (না থাকলে default) */
function setting_get(Database $db, string $key, ?string $default = null): ?string
{
    $row = $db->fetch("SELECT svalue FROM settings WHERE skey = ?", [$key]);
    return $row ? $row['svalue'] : $default;
}

/** একটি সেটিং সংরক্ষণ (থাকলে আপডেট, নইলে নতুন) */
function setting_set(Database $db, string $key, string $value): void
{
    if ($db->fetch("SELECT skey FROM settings WHERE skey = ?", [$key])) {
        $db->update('settings', ['svalue' => $value], 'skey = ?', [$key]);
    } else {
        $db->insert('settings', ['skey' => $key, 'svalue' => $value]);
    }
}

/**
 * App Center-এর আপলোড/শেয়ার বেস পাথ।
 * আগে Settings-এ সংরক্ষিত মান, নইলে config.php-এর NETWORK_SHARE_BASE।
 */
function appcenter_share_path(Database $db): string
{
    $p = setting_get($db, 'appcenter_share_path', NETWORK_SHARE_BASE);
    return rtrim($p, '\\/');
}

/**
 * একটি অ্যাপে guest কী কী করতে পারবে তা হিসাব করে।
 * - লগইন করা থাকলে সব true (admin সবসময় সব দেখে)।
 * - guest হলে: অ্যাপের নিজস্ব override থাকলে সেটা, নইলে গ্লোবাল সেটিং।
 * ফেরত: ['install'=>bool,'update'=>bool,'uninstall'=>bool,'download'=>bool]
 */
function appcenter_allowed_actions(Database $db, array $app, bool $guest): array
{
    if (!$guest) {
        return ['install' => true, 'update' => true, 'uninstall' => true, 'download' => true];
    }
    $ga = $app['guest_actions'] ?? null;
    if ($ga !== null && $ga !== '') {          // per-app override
        $set = $ga === 'none' ? [] : array_map('trim', explode(',', $ga));
        return [
            'install'   => in_array('install',   $set, true),
            'update'    => in_array('update',    $set, true),
            'uninstall' => in_array('uninstall', $set, true),
            'download'  => in_array('download',  $set, true),
        ];
    }
    // গ্লোবাল guest সেটিং
    return [
        'install'   => setting_get($db, 'appcenter_guest_install',   '1') === '1',
        'update'    => setting_get($db, 'appcenter_guest_update',    '0') === '1',
        'uninstall' => setting_get($db, 'appcenter_guest_uninstall', '0') === '1',
        'download'  => setting_get($db, 'appcenter_guest_download',  '1') === '1',
    ];
}
