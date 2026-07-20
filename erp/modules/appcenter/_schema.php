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
}
