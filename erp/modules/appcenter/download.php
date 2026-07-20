<?php
/**
 * download.php — নেটওয়ার্ক শেয়ার থেকে ইনস্টলার ফাইল ব্রাউজারে পাঠায়।
 * (ব্রাউজার সরাসরি exe চালাতে পারে না, তাই ডাউনলোড করে ইউজার রান করবে।)
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requirePermission('appcenter.view');

$db  = Database::getInstance();
$id  = (int) input('id');
$app = $db->fetch("SELECT * FROM apps WHERE id = ? AND is_active = 1", [$id]);

if (!$app) {
    http_response_code(404);
    exit('অ্যাপ পাওয়া যায়নি।');
}

$path = resolve_app_path($app['network_path']);

if (!is_file($path)) {
    http_response_code(404);
    exit('ফাইল খুঁজে পাওয়া যায়নি: ' . e($path) . "\n\nনেটওয়ার্ক পাথ ঠিক আছে কিনা এবং সার্ভার শেয়ারে অ্যাক্সেস আছে কিনা দেখুন।");
}

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
