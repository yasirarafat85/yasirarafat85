<?php
// auth/register.php — নতুন অ্যাকাউন্ট তৈরি
require_once __DIR__ . '/../includes/auth.php';
$base = '../';

if (current_user()) redirect('../index.php');

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = clean($_POST['name'] ?? '');
    $email = strtolower(clean($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($name === '')                          $errors[] = 'নাম দিন।';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'সঠিক ইমেইল দিন।';
    if (strlen($pass) < 6)                     $errors[] = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।';
    if ($pass !== $pass2)                      $errors[] = 'দুটি পাসওয়ার্ড মেলেনি।';

    if (!$errors) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'এই ইমেইলে ইতিমধ্যে অ্যাকাউন্ট আছে।';
        } else {
            $stmt = db()->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            login_user((int)db()->lastInsertId());
            set_flash('success', 'স্বাগতম, ' . $name . '! অ্যাকাউন্ট তৈরি হয়েছে।');
            redirect('../index.php');
        }
    }
}

$pageTitle = 'রেজিস্টার';
require __DIR__ . '/../partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h4 class="card-title mb-3">নতুন অ্যাকাউন্ট 📝</h4>
            <?php foreach ($errors as $er): ?>
                <div class="alert alert-danger py-2"><?= e($er) ?></div>
            <?php endforeach; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">নাম</label>
                    <input type="text" name="name" class="form-control" value="<?= e($name) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">ইমেইল</label>
                    <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">পাসওয়ার্ড</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">পাসওয়ার্ড আবার</label>
                    <input type="password" name="password2" class="form-control" required>
                </div>
                <button class="btn btn-brand w-100">অ্যাকাউন্ট খুলুন</button>
            </form>
            <p class="text-center mt-3 mb-0">অ্যাকাউন্ট আছে? <a href="login.php">লগইন</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
