<?php
/**
 * api.php — silent-install helper স্ক্রিপ্ট এই এন্ডপয়েন্টে কল করে।
 *
 * GET  ?id=..&t=..&pc=..   → টোকেন যাচাই করে ইনস্টল-তথ্য (JSON) দেয়
 *                            এবং একটি install_logs entry (status=started) বানায়।
 * POST action=report        → helper ইনস্টল শেষে ফলাফল (success/failed) জানায়,
 *                            log entry আপডেট হয়।
 *
 * নিরাপত্তা: token সার্ভার-সাইড সিক্রেট দিয়ে সই করা ও দিন-ভিত্তিক।
 * সার্ভারে সংরক্ষিত অ্যাপ ছাড়া কিছুই চালানো যায় না।
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/_schema.php';
header('Content-Type: application/json; charset=utf-8');

$id    = (int) input('id');
$token = (string) input('t', '');
$pc    = substr(trim((string) input('pc', '')), 0, 120) ?: null;

// টোকেন আগে যাচাই — বৈধ না হলে ডাটাবেসে হাত দেওয়ার আগেই থামাই
if ($id <= 0 || !app_token_valid($id, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'অবৈধ বা মেয়াদোত্তীর্ণ টোকেন']);
    exit;
}

$db = Database::getInstance();
appcenter_ensure_schema($db);

// ---------- helper ইনস্টল শেষে ফলাফল জানাচ্ছে ----------
if (input('action') === 'report') {
    $logId  = (int) input('log_id');
    $status = input('status') === 'success' ? 'success' : 'failed';
    $note   = substr(trim((string) input('note', '')), 0, 255) ?: null;
    if ($logId > 0) {
        $db->update('install_logs', ['status' => $status, 'note' => $note], 'id = ?', [$logId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ---------- helper ইনস্টলের তথ্য চাইছে ----------
$app = $db->fetch("SELECT * FROM apps WHERE id = ? AND is_active = 1", [$id]);
if (!$app) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'অ্যাপ পাওয়া যায়নি']);
    exit;
}

// লগে "started" entry বানাই
$logId = $db->insert('install_logs', [
    'app_id'     => $app['id'],
    'app_name'   => $app['name'],
    'method'     => 'silent',
    'status'     => 'started',
    'pc_name'    => $pc,
    'ip_address' => client_ip(),
]);

$resp = [
    'ok'      => true,
    'log_id'  => $logId,
    'name'    => $app['name'],
    'type'    => $app['install_type'],       // network | winget
    'args'    => $app['silent_args'] ?? '',
];
if ($app['install_type'] === 'winget') {
    $resp['winget_id'] = $app['winget_id'];
} else {
    $resp['path'] = resolve_app_path($app['network_path'] ?? '');
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
