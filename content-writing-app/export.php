<?php
// export.php — লেখা .txt ফাইল হিসেবে ডাউনলোড (মালিকানা যাচাই সহ)
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT title, body FROM writings WHERE id = ? AND user_id = ?');
$st->execute([$id, $user['id']]);
$w = $st->fetch();

if (!$w) {
    set_flash('danger', 'লেখা পাওয়া যায়নি।');
    redirect('writings.php');
}

$filename = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $w['title']) ?: 'writing';
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
echo $w['title'] . "\n\n" . $w['body'];
exit;
