<?php
/**
 * download.php — নেটওয়ার্ক শেয়ার থেকে ইনস্টলার ফাইল ব্রাউজারে পাঠায়।
 * (ব্রাউজার সরাসরি exe চালাতে পারে না, তাই ডাউনলোড করে ইউজার রান করবে।)
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/_schema.php';

$db  = Database::getInstance();
appcenter_ensure_schema($db);

// guest mode বন্ধ থাকলে লগইন লাগবে
$isGuest = !Auth::check();
if ($isGuest && setting_get($db, 'appcenter_guest', '0') !== '1') {
    Auth::requirePermission('appcenter.view');
}

$id  = (int) input('id');
$app = $db->fetch("SELECT * FROM apps WHERE id = ? AND is_active = 1", [$id]);

if (!$app) {
    http_response_code(404);
    exit('অ্যাপ পাওয়া যায়নি।');
}

// guest হলে এই অ্যাপে download অনুমোদিত কিনা (per-app বা গ্লোবাল)
if ($isGuest) {
    $allow = appcenter_allowed_actions($db, $app, true);
    if (!$allow['download']) {
        http_response_code(403);
        exit('এই অ্যাপে guest ডাউনলোড অনুমোদিত নয়।');
    }
}

// winget অ্যাপ ডাউনলোড হয় না (কোনো ফাইল নেই — winget নিজে নামায়)
if ($app['install_type'] === 'winget') {
    http_response_code(400);
    exit('এই অ্যাপটি winget দিয়ে ইনস্টল হয় — ডাউনলোডের ফাইল নেই। "Install" বাটন ব্যবহার করুন।');
}

$path = resolve_app_path($app['network_path'] ?? '');
$u    = Auth::user();

if (!is_file($path)) {
    // ব্যর্থ ডাউনলোড লগ করি
    $db->insert('install_logs', [
        'app_id' => $app['id'], 'app_name' => $app['name'], 'method' => 'download',
        'status' => 'failed', 'user_id' => $u['id'] ?? null, 'user_name' => $u['name'] ?? null,
        'ip_address' => client_ip(), 'note' => 'file not found',
    ]);
    http_response_code(404);
    exit('ফাইল খুঁজে পাওয়া যায়নি: ' . e($path) . "\n\nনেটওয়ার্ক পাথ ঠিক আছে কিনা এবং সার্ভার শেয়ারে অ্যাক্সেস আছে কিনা দেখুন।");
}

// সফল ডাউনলোড লগ করি (ব্রাউজার হোস্টনেম দেয় না, তাই IP + ইউজার রাখি)
$db->insert('install_logs', [
    'app_id' => $app['id'], 'app_name' => $app['name'], 'method' => 'download',
    'status' => 'success', 'user_id' => $u['id'] ?? null, 'user_name' => $u['name'] ?? null,
    'ip_address' => client_ip(), 'pc_name' => null,
]);

$filename = basename(str_replace('\\', '/', $path));

// ব্রাউজারকে ডাউনলোড হিসেবে পাঠানোর হেডার
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

// বড় ফাইল হলেও মেমরি না ভরে চাংক করে পাঠাই
if (ob_get_level()) ob_end_clean();
$fp = fopen($path, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 1024 * 256); // 256KB
        flush();
    }
    fclose($fp);
}
exit;
