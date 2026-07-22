<?php
// auth/login.php — লগইন
require_once __DIR__ . '/../includes/auth.php';
$base = '../';

if (current_user()) redirect('../index.php');

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(clean($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        login_user((int)$user['id']);
        set_flash('success', 'আবার স্বাগতম, ' . $user['name'] . '!');
        redirect('../index.php');
    } else {
        $errors[] = 'ইমেইল বা পাসওয়ার্ড ভুল।';
    }
}

$pageTitle = 'লগইন';
require __DIR__ . '/../partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h4 class="card-title mb-3">লগইন 🔑</h4>
            <?php foreach ($errors as $er): ?>
                <div class="alert alert-danger py-2"><?= e($er) ?></div>
            <?php endforeach; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">ইমেইল</label>
                    <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">পাসওয়ার্ড</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-brand w-100">লগইন</button>
            </form>
            <p class="text-center mt-3 mb-0">অ্যাকাউন্ট নেই? <a href="register.php">রেজিস্টার করুন</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
