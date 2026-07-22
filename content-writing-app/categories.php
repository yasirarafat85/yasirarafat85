<?php
// categories.php — ৪টা ক্যাটাগরির তালিকা (লেভেল ১ শেখার প্রবেশদ্বার)
require_once __DIR__ . '/includes/auth.php';
require_login();

$cats = db()->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$icons = ['facebook' => '📱', 'sales' => '💼', 'blog' => '📚', 'story' => '✍️'];

$pageTitle = 'শেখা';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-1">শেখা — লেভেল ১ 📚</h3>
<p class="text-muted">একটা ক্যাটাগরি বেছে নাও — টিপস, উদাহরণ ও চ্যালেঞ্জ পাবে।</p>

<div class="row g-3">
    <?php foreach ($cats as $c): ?>
        <div class="col-md-6">
            <div class="card p-4 h-100">
                <h5 class="card-title"><?= $icons[$c['slug']] ?? '📝' ?> <?= e($c['name']) ?></h5>
                <p class="text-muted mb-3"><?= e($c['description']) ?></p>
                <div class="mt-auto d-flex gap-2">
                    <a href="learn.php?slug=<?= e($c['slug']) ?>" class="btn btn-brand btn-sm">শেখো</a>
                    <a href="generate.php?category_id=<?= (int)$c['id'] ?>" class="btn btn-accent btn-sm">খসড়া বানাও</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
