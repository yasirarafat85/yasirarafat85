<?php
/**
 * ---------------------------------------------------------------
 *  install.php — এক-ক্লিক ইনস্টলার
 * ---------------------------------------------------------------
 *  ব্রাউজারে খুলুন:  http://localhost/erp/install.php
 *  এটি টেবিল তৈরি করবে, ডিফল্ট role/permission/menu ও একটি
 *  Super Admin অ্যাকাউন্ট বানাবে। শেষে এই ফাইল মুছে ফেলবেন।
 */
require_once __DIR__ . '/config/config.php';

// প্রথমে ডাটাবেস (না থাকলে) তৈরি করি
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
} catch (PDOException $e) {
    die('ডাটাবেস তৈরি ব্যর্থ: ' . $e->getMessage());
}

// স্কিমা চালাই
$schema = file_get_contents(__DIR__ . '/database/schema.sql');
$pdo->exec($schema);

require_once __DIR__ . '/core/Database.php';
$db = Database::getInstance();

$messages = [];

// ---------- Roles ----------
if (!$db->fetch("SELECT id FROM roles WHERE name = ?", ['Super Admin'])) {
    $db->insert('roles', ['name' => 'Super Admin', 'description' => 'সম্পূর্ণ নিয়ন্ত্রণ', 'is_locked' => 1]);
    $db->insert('roles', ['name' => 'Manager', 'description' => 'বেশিরভাগ কাজ, সেটিংস ছাড়া', 'is_locked' => 1]);
    $db->insert('roles', ['name' => 'Staff', 'description' => 'সীমিত অ্যাক্সেস', 'is_locked' => 1]);
    $messages[] = 'Roles তৈরি হয়েছে';
}
$superId   = $db->scalar("SELECT id FROM roles WHERE name = 'Super Admin'");
$managerId = $db->scalar("SELECT id FROM roles WHERE name = 'Manager'");

// ---------- Permissions ----------
$permissions = [
    // system
    ['Dashboard দেখা',        'dashboard.view',   'system'],
    ['ইউজার দেখা',           'users.view',       'system'],
    ['ইউজার তৈরি',           'users.create',     'system'],
    ['ইউজার এডিট',           'users.edit',       'system'],
    ['ইউজার ডিলিট',          'users.delete',     'system'],
    ['Role ও Permission',     'roles.manage',     'system'],
    ['মেনু ব্যবস্থাপনা',       'menus.manage',     'system'],
    // example module: inventory
    ['ইনভেন্টরি দেখা',        'inventory.view',   'inventory'],
    ['ইনভেন্টরি তৈরি',        'inventory.create', 'inventory'],
    ['ইনভেন্টরি এডিট',        'inventory.edit',   'inventory'],
    ['ইনভেন্টরি ডিলিট',       'inventory.delete', 'inventory'],
];
foreach ($permissions as [$name, $slug, $module]) {
    if (!$db->fetch("SELECT id FROM permissions WHERE slug = ?", [$slug])) {
        $db->insert('permissions', ['name' => $name, 'slug' => $slug, 'module' => $module]);
    }
}
$messages[] = 'Permissions তৈরি হয়েছে';

// Super Admin-কে সব permission দাও
$allPermIds = array_column($db->fetchAll("SELECT id FROM permissions"), 'id');
foreach ($allPermIds as $pid) {
    if (!$db->fetch("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?", [$superId, $pid])) {
        $db->insert('role_permissions', ['role_id' => $superId, 'permission_id' => $pid]);
    }
}
// Manager-কে ইনভেন্টরি + dashboard দাও (উদাহরণ)
$managerSlugs = ['dashboard.view', 'inventory.view', 'inventory.create', 'inventory.edit'];
foreach ($managerSlugs as $slug) {
    $pid = $db->scalar("SELECT id FROM permissions WHERE slug = ?", [$slug]);
    if ($pid && !$db->fetch("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?", [$managerId, $pid])) {
        $db->insert('role_permissions', ['role_id' => $managerId, 'permission_id' => $pid]);
    }
}

// ---------- Default Menus ----------
if (!$db->fetch("SELECT id FROM menus LIMIT 1")) {
    $db->insert('menus', ['title' => 'Dashboard',  'url' => 'index.php',            'icon' => 'bi-speedometer2', 'permission' => 'dashboard.view', 'sort_order' => 1]);
    $db->insert('menus', ['title' => 'Inventory',  'url' => 'modules/inventory/index.php', 'icon' => 'bi-box-seam', 'permission' => 'inventory.view', 'sort_order' => 2]);
    $settingsId = $db->insert('menus', ['title' => 'Settings', 'url' => '#', 'icon' => 'bi-gear', 'permission' => null, 'sort_order' => 90]);
    $db->insert('menus', ['parent_id' => $settingsId, 'title' => 'Users',       'url' => 'users.php', 'icon' => 'bi-people',       'permission' => 'users.view',   'sort_order' => 1]);
    $db->insert('menus', ['parent_id' => $settingsId, 'title' => 'Roles',       'url' => 'roles.php', 'icon' => 'bi-shield-lock',  'permission' => 'roles.manage', 'sort_order' => 2]);
    $db->insert('menus', ['parent_id' => $settingsId, 'title' => 'Menu Manager','url' => 'menus.php', 'icon' => 'bi-list-nested',  'permission' => 'menus.manage', 'sort_order' => 3]);
    $messages[] = 'Menus তৈরি হয়েছে';
}

// ---------- Default Super Admin User ----------
$adminEmail = 'admin@erp.local';
if (!$db->fetch("SELECT id FROM users WHERE email = ?", [$adminEmail])) {
    $db->insert('users', [
        'name'     => 'Super Admin',
        'email'    => $adminEmail,
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role_id'  => $superId,
        'status'   => 1,
    ]);
    $messages[] = 'Super Admin অ্যাকাউন্ট তৈরি হয়েছে';
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install — <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <style>
    body{font-family:'Inter',sans-serif;background:#0F172A;color:#F1F5F9;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px}
    .box{background:#1E293B;border:1px solid #334155;border-radius:16px;padding:40px;max-width:520px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.4)}
    h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;margin:0 0 4px}
    .ok{color:#26C6A0}
    ul{padding-left:18px;color:#94A3B8;line-height:1.9}
    .cred{background:#0F172A;border:1px dashed #334155;border-radius:10px;padding:16px;margin:20px 0}
    .cred b{color:#F1F5F9}
    a.btn{display:inline-block;margin-top:12px;background:linear-gradient(to right,#26C6A0,#00897B);color:#fff;text-decoration:none;padding:13px 24px;border-radius:10px;font-weight:600}
    .warn{color:#FFA726;font-size:13px;margin-top:20px}
  </style>
</head>
<body>
  <div class="box">
    <h1>✅ ইনস্টলেশন সম্পন্ন</h1>
    <p style="color:#94A3B8">ERP Foundation সফলভাবে সেটআপ হয়েছে।</p>
    <ul>
      <?php foreach ($messages as $m): ?><li class="ok"><?= e($m) ?></li><?php endforeach; ?>
    </ul>
    <div class="cred">
      <div>লগইন তথ্য (Super Admin):</div>
      <div>📧 Email: <b><?= $adminEmail ?></b></div>
      <div>🔑 Password: <b>admin123</b></div>
    </div>
    <a class="btn" href="<?= url('auth/login.php') ?>">লগইন পেজে যান →</a>
    <p class="warn">⚠️ নিরাপত্তার জন্য এখন <b>install.php</b> ফাইলটি মুছে ফেলুন এবং প্রথম লগইনের পর পাসওয়ার্ড পরিবর্তন করুন।</p>
  </div>
</body>
</html>
