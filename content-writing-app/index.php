<?php
// index.php — ড্যাশবোর্ড (পরিসংখ্যান + সর্বশেষ লেখা)
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$uid = $user['id'];

// মোট লেখা
$total = db()->prepare('SELECT COUNT(*) AS n FROM writings WHERE user_id = ?');
$total->execute([$uid]);
$totalCount = (int)$total->fetch()['n'];

// ক্যাটাগরি অনুযায়ী সংখ্যা
$byCat = db()->prepare("
    SELECT c.name, COUNT(w.id) AS n
    FROM categories c
    LEFT JOIN writings w ON w.category_id = c.id AND w.user_id = ?
    GROUP BY c.id ORDER BY c.id
");
$byCat->execute([$uid]);
$cats = $byCat->fetchAll();

// মোট শব্দ
$words = db()->prepare('SELECT COALESCE(SUM(word_count),0) AS n FROM writings WHERE user_id = ?');
$words->execute([$uid]);
$totalWords = (int)$words->fetch()['n'];

// সর্বশেষ ৫টা
$recent = db()->prepare("
    SELECT w.*, c.name AS cat_name
    FROM writings w LEFT JOIN categories c ON c.id = w.category_id
    WHERE w.user_id = ? ORDER BY w.updated_at DESC LIMIT 5
");
$recent->execute([$uid]);
$recentRows = $recent->fetchAll();

$pageTitle = 'ড্যাশবোর্ড';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-1">স্বাগতম, <?= e($user['name']) ?>! 👋</h3>
<p class="text-muted">আজ কী লিখবে? চলো অনুশীলন করি।</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-tile"><div class="num"><?= $totalCount ?></div><div class="lbl">মোট লেখা</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-tile"><div class="num"><?= $totalWords ?></div><div class="lbl">মোট শব্দ</div></div></div>
    <?php foreach (array_slice($cats, 0, 2) as $c): ?>
        <div class="col-6 col-md-3"><div class="stat-tile"><div class="num"><?= (int)$c['n'] ?></div><div class="lbl"><?= e($c['name']) ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">সর্বশেষ লেখা</h5>
                <a href="writing_edit.php" class="btn btn-sm btn-brand">+ নতুন লেখা</a>
            </div>
            <?php if (!$recentRows): ?>
                <p class="text-muted">এখনো কোনো লেখা নেই। <a href="categories.php">শেখা শুরু করো</a> অথবা <a href="generate.php">খসড়া বানাও</a>।</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentRows as $r): ?>
                        <a href="writing_view.php?id=<?= (int)$r['id'] ?>" class="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                            <span><?= e($r['title']) ?>
                                <?php if ($r['cat_name']): ?><span class="category-badge ms-2"><?= e($r['cat_name']) ?></span><?php endif; ?>
                            </span>
                            <small class="text-muted"><?= (int)$r['word_count'] ?> শব্দ</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <h5 class="card-title mb-3">দ্রুত শুরু</h5>
            <div class="d-grid gap-2">
                <a href="categories.php" class="btn btn-brand">📚 শেখা (লেভেল ১)</a>
                <a href="generate.php" class="btn btn-accent">⚡ খসড়া বানাও (লেভেল ২)</a>
                <a href="ai_write.php" class="btn btn-accent">✨ AI লিখে দাও (লেভেল ৩)</a>
                <a href="writings.php" class="btn btn-outline-secondary">📄 আমার লেখা</a>
                <a href="settings.php" class="btn btn-outline-secondary">🤖 AI সেটিংস</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
