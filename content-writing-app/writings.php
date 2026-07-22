<?php
// writings.php — আমার লেখার তালিকা (সার্চ ও ফিল্টার সহ)
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$uid = $user['id'];

$q   = clean($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);

$sql = "SELECT w.*, c.name AS cat_name FROM writings w
        LEFT JOIN categories c ON c.id = w.category_id
        WHERE w.user_id = ?";
$params = [$uid];
if ($q !== '') { $sql .= " AND w.title LIKE ?"; $params[] = '%' . $q . '%'; }
if ($cat)     { $sql .= " AND w.category_id = ?"; $params[] = $cat; }
$sql .= " ORDER BY w.updated_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$cats = db()->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();

$pageTitle = 'আমার লেখা';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">📄 আমার লেখা</h3>
    <a href="writing_edit.php" class="btn btn-brand btn-sm">+ নতুন লেখা</a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-6"><input type="text" name="q" class="form-control" placeholder="শিরোনাম খুঁজুন..." value="<?= e($q) ?>"></div>
    <div class="col-md-4">
        <select name="cat" class="form-select">
            <option value="0">সব ক্যাটাগরি</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary">খুঁজুন</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card p-4 text-center text-muted">কোনো লেখা পাওয়া যায়নি। <a href="writing_edit.php">নতুন লেখা শুরু করো</a>।</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($rows as $r): ?>
            <div class="col-md-6">
                <div class="card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><?= e($r['title']) ?></h6>
                        <?php if ($r['cat_name']): ?><span class="category-badge"><?= e($r['cat_name']) ?></span><?php endif; ?>
                    </div>
                    <p class="text-muted mb-2"><small><?= e(mb_substr($r['body'], 0, 90)) ?><?= mb_strlen($r['body']) > 90 ? '…' : '' ?></small></p>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <small class="text-muted"><?= (int)$r['word_count'] ?> শব্দ · <?= reading_time((int)$r['word_count']) ?> মিনিট
                            <?php if ($r['ai_score'] !== null): ?>· ⭐ <?= (int)$r['ai_score'] ?>/১০<?php endif; ?>
                        </small>
                        <div>
                            <a href="writing_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">দেখো</a>
                            <a href="writing_edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-brand">এডিট</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
