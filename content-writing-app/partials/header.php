<?php
// partials/header.php — <head>, ন্যাভবার ও flash বার্তা
// পেজ include করার আগে চাইলে সেট করতে পারে: $pageTitle, $base

// নিরাপত্তা হেডার (আউটপুট শুরুর আগে পাঠাতে হয়)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');       // MIME sniffing বন্ধ
    header('X-Frame-Options: SAMEORIGIN');            // clickjacking ঠেকায়
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

$base = $base ?? '';                 // auth/ সাবফোল্ডার হলে '../' দেবে
$pageTitle = $pageTitle ?? APP_NAME;
$u = current_user();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= $base ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $base ?>index.php">✍️ <?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto">
                <?php if ($u): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>index.php">ড্যাশবোর্ড</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>categories.php">শেখা</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>generate.php">খসড়া বানাও</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>ai_write.php">AI লিখে দাও</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>writings.php">আমার লেখা</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>settings.php">সেটিংস</a></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="<?= $base ?>auth/logout.php">লগআউট (<?= e($u['name']) ?>)</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>auth/login.php">লগইন</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>auth/register.php">রেজিস্টার</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
