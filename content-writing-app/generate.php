<?php
// generate.php — লেভেল ২: টেমপ্লেটে ঘর পূরণ করে খসড়া তৈরি (API ছাড়া)
require_once __DIR__ . '/includes/auth.php';
require_login();

// প্লেসহোল্ডার বের করা: {ঘরের_নাম}
function extract_placeholders(string $structure): array
{
    preg_match_all('/\{([^}]+)\}/u', $structure, $m);
    return array_values(array_unique($m[1]));
}

$templateId = (int)($_GET['template_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);

$draft = '';
$selectedTemplate = null;

// টেমপ্লেট বাছাই করা থাকলে সেটি লোড করো
if ($templateId) {
    $st = db()->prepare('SELECT t.*, c.name AS cat_name FROM templates t JOIN categories c ON c.id = t.category_id WHERE t.id = ?');
    $st->execute([$templateId]);
    $selectedTemplate = $st->fetch();
    if ($selectedTemplate) $categoryId = (int)$selectedTemplate['category_id'];
}

// ফর্ম জমা → খসড়া তৈরি
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedTemplate) {
    verify_csrf();
    $draft = $selectedTemplate['structure'];
    $fields = $_POST['field'] ?? [];
    foreach (extract_placeholders($selectedTemplate['structure']) as $ph) {
        $val = clean($fields[$ph] ?? '');
        $draft = str_replace('{' . $ph . '}', $val !== '' ? $val : '...', $draft);
    }
}

$pageTitle = 'খসড়া বানাও';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-1">খসড়া বানাও — লেভেল ২ ⚡</h3>
<p class="text-muted">ঘরগুলো পূরণ করো, অ্যাপ ফর্মুলায় বসিয়ে খসড়া বানিয়ে দেবে (AI ছাড়াই)।</p>

<?php if (!$selectedTemplate): ?>
    <?php
    // টেমপ্লেট তালিকা দেখাও
    if ($categoryId) {
        $tq = db()->prepare('SELECT t.*, c.name AS cat_name FROM templates t JOIN categories c ON c.id=t.category_id WHERE t.category_id=? ORDER BY t.id');
        $tq->execute([$categoryId]);
    } else {
        $tq = db()->query('SELECT t.*, c.name AS cat_name FROM templates t JOIN categories c ON c.id=t.category_id ORDER BY t.category_id, t.id');
    }
    $templates = $tq->fetchAll();
    $grouped = [];
    foreach ($templates as $t) $grouped[$t['cat_name']][] = $t;
    ?>
    <?php foreach ($grouped as $catName => $tpls): ?>
        <h6 class="mt-3 mb-2 text-muted"><?= e($catName) ?></h6>
        <div class="row g-3 mb-2">
            <?php foreach ($tpls as $t): ?>
                <div class="col-md-6">
                    <div class="card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= e($t['name']) ?></strong>
                                <div><small class="text-muted"><?= e($t['formula']) ?></small></div>
                            </div>
                            <a href="generate.php?template_id=<?= (int)$t['id'] ?>" class="btn btn-brand btn-sm">বেছে নাও</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

<?php else: ?>
    <?php $placeholders = extract_placeholders($selectedTemplate['structure']); ?>
    <a href="generate.php?category_id=<?= (int)$categoryId ?>" class="btn btn-sm btn-outline-secondary mb-3">← অন্য টেমপ্লেট</a>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card p-4">
                <h5 class="card-title"><?= e($selectedTemplate['name']) ?>
                    <span class="category-badge ms-1"><?= e($selectedTemplate['cat_name']) ?></span></h5>
                <p class="text-muted"><small>ফর্মুলা: <?= e($selectedTemplate['formula']) ?></small></p>
                <form method="post">
                    <?= csrf_field() ?>
                    <?php foreach ($placeholders as $ph): ?>
                        <div class="mb-3">
                            <label class="form-label"><?= e(str_replace('_', ' ', $ph)) ?></label>
                            <textarea name="field[<?= e($ph) ?>]" class="form-control" rows="2"><?= e($_POST['field'][$ph] ?? '') ?></textarea>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-accent w-100">⚡ খসড়া তৈরি করো</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 h-100">
                <h5 class="card-title">📄 তোমার খসড়া</h5>
                <?php if ($draft !== ''): ?>
                    <div class="draft-output mb-3"><?= e($draft) ?></div>
                    <form method="post" action="writing_edit.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="prefill" value="1">
                        <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                        <input type="hidden" name="title" value="<?= e($selectedTemplate['name']) ?> খসড়া">
                        <textarea name="body" class="d-none"><?= e($draft) ?></textarea>
                        <button class="btn btn-brand w-100">✏️ এই খসড়া এডিট করে সেভ করো</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">বাঁ দিকের ঘরগুলো পূরণ করে "খসড়া তৈরি করো" চাপো — এখানে ফলাফল দেখাবে।</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
