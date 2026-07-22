<?php
// ai_write.php — লেভেল ৩: ইউজারের নির্দেশ থেকে AI নতুন কনটেন্ট লেখে
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai_client.php';
$user = require_login();
$uid = $user['id'];
global $AI_PROVIDERS;

$cats = db()->query('SELECT id, name, slug FROM categories ORDER BY id')->fetchAll();
$settings = get_ai_settings($uid);
$providerModels = $AI_PROVIDERS[$settings['provider']]['models'] ?? [];

$generated = '';
$error = '';
$brief = '';
$categoryId = (int)($_GET['category_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $brief      = clean($_POST['brief'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $model      = clean($_POST['model'] ?? '') ?: null;

    if ($brief === '') {
        $error = 'কী লিখতে হবে সেটা লিখুন।';
    } else {
        // ক্যাটাগরি অনুযায়ী নির্দেশনা
        $catName = '';
        foreach ($cats as $c) if ((int)$c['id'] === $categoryId) $catName = $c['name'];
        $catHint = $catName ? "লেখাটি হবে '{$catName}' ধরনের। " : '';

        $system = "তুমি একজন দক্ষ বাংলা কনটেন্ট রাইটার। {$catHint}"
            . "ব্যবহারকারীর নির্দেশ অনুযায়ী স্বাভাবিক, আকর্ষণীয় ও অর্গানিক বাংলা কনটেন্ট লেখো। "
            . "শুধু লেখাটাই দাও, অতিরিক্ত ব্যাখ্যা নয়। প্রথম লাইনে একটা শক্তিশালী হুক রাখো।";

        $r = ai_chat($uid, $system, $brief, $model);
        if ($r['ok']) {
            $generated = $r['text'];
        } else {
            $error = $r['error'];
        }
    }
}

$pageTitle = 'AI লিখে দাও';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-1">🤖 AI লিখে দাও — লেভেল ৩</h3>
<p class="text-muted">কী লিখতে চাও বলো, AI খসড়া বানিয়ে দেবে। তারপর নিজের মতো এডিট করে সেভ করো।</p>

<?php if (!ai_available($uid)): ?>
    <div class="alert alert-warning">AI ফিচার চালু নেই। আগে <a href="settings.php">সেটিংসে API key</a> দিন।</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-md-5">
            <div class="card p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">কী লিখতে হবে?</label>
                        <textarea name="brief" class="form-control" rows="5" placeholder="যেমন: একটা হাতে বানানো মোমবাতির জন্য ফেসবুক সেলস পোস্ট লেখো, দাম ৩৯০ টাকা।" required><?= e($brief) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ক্যাটাগরি</label>
                        <select name="category_id" class="form-select">
                            <option value="0">— নির্বাচন করো —</option>
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">মডেল (ঐচ্ছিক ওভাররাইড)</label>
                        <select name="model" class="form-select">
                            <option value="">ডিফল্ট (<?= e($settings['default_model']) ?>)</option>
                            <?php foreach ($providerModels as $mo): ?>
                                <option value="<?= e($mo) ?>"><?= e($mo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-accent w-100" id="writeBtn">✨ লিখে দাও</button>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card p-4 h-100">
                <h6 class="card-title">📄 AI-র লেখা</h6>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($generated !== ''): ?>
                    <div class="draft-output mb-3"><?= e($generated) ?></div>
                    <form method="post" action="writing_edit.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="prefill" value="1">
                        <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                        <input type="hidden" name="title" value="AI খসড়া">
                        <textarea name="body" class="d-none"><?= e($generated) ?></textarea>
                        <button class="btn btn-brand w-100">✏️ এডিট করে সেভ করো</button>
                    </form>
                <?php elseif (!$error): ?>
                    <p class="text-muted">বাঁ দিকে নির্দেশ লিখে "লিখে দাও" চাপো — AI এখানে খসড়া দেবে।</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelector('form')?.addEventListener('submit', function () {
    const b = document.getElementById('writeBtn');
    if (b) { b.disabled = true; b.textContent = '⏳ AI লিখছে...'; }
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
