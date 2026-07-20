<?php
/**
 * api.php — silent-install helper স্ক্রিপ্ট এই এন্ডপয়েন্টে কল করে।
 * অ্যাপ id + token পাঠালে সার্ভার যাচাই করে ইনস্টলারের নেটওয়ার্ক পাথ
 * ও silent আর্গুমেন্ট JSON আকারে ফেরত দেয়।
 *
 * নিরাপত্তা: token সার্ভার-সাইড সিক্রেট দিয়ে সই করা ও দিন-ভিত্তিক।
 * ফলে যেকেউ ইচ্ছেমতো id দিয়ে কল করলেও token ছাড়া কিছু পাবে না,
 * এবং সার্ভারে সংরক্ষিত পাথ ছাড়া অন্য কিছু চালানো যাবে না।
 */
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$id    = (int) input('id');
$token = (string) input('t', '');

if ($id <= 0 || !app_token_valid($id, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'অবৈধ বা মেয়াদোত্তীর্ণ টোকেন']);
    exit;
}

$app = Database::getInstance()->fetch("SELECT * FROM apps WHERE id = ? AND is_active = 1", [$id]);
if (!$app) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'অ্যাপ পাওয়া যায়নি']);
    exit;
}

echo json_encode([
    'ok'      => true,
    'name'    => $app['name'],
    'path'    => resolve_app_path($app['network_path']),
    'args'    => $app['silent_args'] ?? '',
], JSON_UNESCAPED_UNICODE);
