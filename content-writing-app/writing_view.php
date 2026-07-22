<?php
// writing_view.php — একটা লেখা পড়া, ডিলিট, এক্সপোর্ট ও AI অ্যাকশন
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$uid = $user['id'];

$id = (int)($_GET['id'] ?? 0);

// ডিলিট (POST, মালিকানা যাচাই সহ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_delete'])) {
    verify_csrf();
    $del = db()->prepare('DELETE FROM writings WHERE id = ? AND user_id = ?');
    $del->execute([(int)$_POST['id'], $uid]);
    set_flash('info', 'লেখা মুছে ফেলা হয়েছে।');
    redirect('writings.php');
}

$st = db()->prepare('SELECT w.*, c.name AS cat_name FROM writings w LEFT JOIN categories c ON c.id=w.category_id WHERE w.id=? AND w.user_id=?');
$st->execute([$id, $uid]);
$w = $st->fetch();
if (!$w) {
    set_flash('danger', 'লেখা পাওয়া যায়নি।');
    redirect('writings.php');
}

$pageTitle = $w['title'];
require __DIR__ . '/partials/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="writings.php">আমার লেখা</a></li>
    <li class="breadcrumb-item active"><?= e($w['title']) ?></li>
</ol></nav>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <h3 class="mb-0"><?= e($w['title']) ?></h3>
        <?php if ($w['cat_name']): ?><span class="category-badge"><?= e($w['cat_name']) ?></span><?php endif; ?>
    </div>
    <p class="text-muted"><small><?= (int)$w['word_count'] ?> শব্দ · <?= reading_time((int)$w['word_count']) ?> মিনিট পড়া · সর্বশেষ: <?= e($w['updated_at']) ?></small></p>

    <div class="example-box my-3"><?= e($w['body']) ?></div>

    <?php if ($w['ai_feedback']): ?>
        <div class="alert alert-info">
            <strong>🤖 AI ফিডব্যাক <?php if ($w['ai_score'] !== null): ?>(স্কোর: <?= (int)$w['ai_score'] ?>/১০)<?php endif; ?></strong>
            <div class="mt-2" style="white-space:pre-wrap;"><?= e($w['ai_feedback']) ?></div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2 flex-wrap mt-2">
        <a href="writing_edit.php?id=<?= (int)$w['id'] ?>" class="btn btn-brand btn-sm">✏️ এডিট</a>
        <a href="ai_review.php?id=<?= (int)$w['id'] ?>" class="btn btn-accent btn-sm">🤖 AI যাচাই করো</a>
        <a href="export.php?id=<?= (int)$w['id'] ?>" class="btn btn-outline-secondary btn-sm">⬇️ .txt ডাউনলোড</a>
        <form method="post" onsubmit="return confirm('সত্যিই মুছে ফেলবে?');" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
            <button name="do_delete" value="1" class="btn btn-outline-danger btn-sm">🗑️ ডিলিট</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
