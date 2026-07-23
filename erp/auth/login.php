<?php
require_once __DIR__ . '/../bootstrap.php';

if (Auth::check()) redirect('index.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(input('email', ''));
    $pass  = input('password', '');
    if ($email === '' || $pass === '') {
        $error = 'ইমেইল ও পাসওয়ার্ড দুটোই দিন।';
    } elseif (Auth::attempt($email, $pass)) {
        redirect('index.php');
    } else {
        $error = 'ইমেইল বা পাসওয়ার্ড ভুল, অথবা অ্যাকাউন্ট নিষ্ক্রিয়।';
    }
}
?>
<!DOCTYPE html>
<html lang="bn" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>লগইন — <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
  <style>
    body{display:flex;min-height:100vh;align-items:center;justify-content:center;padding:20px;
      background:radial-gradient(1200px 600px at 100% 0,rgba(240,83,64,.12),transparent),var(--bg-secondary)}
    .login-box{width:100%;max-width:410px}
    .login-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;
      padding:38px 34px;box-shadow:var(--shadow)}
    .brand{text-align:center;margin-bottom:26px}
    .brand .logo{width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));
      color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;font-family:'Plus Jakarta Sans'}
    .brand h1{font-size:22px;margin:14px 0 4px}
    .brand p{color:var(--text-muted);font-size:14px;margin:0}
    .btn{width:100%;padding:13px}
    .hint{margin-top:20px;text-align:center;font-size:13px;color:var(--text-muted);
      background:var(--bg-secondary);border-radius:10px;padding:12px}
    .input-wrap{position:relative}
    .input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted)}
    .input-wrap .form-control{padding-left:42px}
  </style>
</head>
<body>
  <div class="login-box">
    <div class="login-card">
      <div class="brand">
        <div class="logo"><?= strtoupper(substr(APP_NAME, 0, 1)) ?></div>
        <h1><?= APP_NAME ?></h1>
        <p>আপনার অ্যাকাউন্টে লগইন করুন</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">ইমেইল</label>
          <div class="input-wrap">
            <i class="bi bi-envelope"></i>
            <input type="email" name="email" class="form-control" placeholder="admin@erp.local"
                   value="<?= e(input('email', '')) ?>" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">পাসওয়ার্ড</label>
          <div class="input-wrap">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> লগইন</button>
      </form>

      <div class="hint">
        ডিফল্ট: <b>admin@erp.local</b> / <b>admin123</b>
      </div>
    </div>
  </div>
</body>
</html>
