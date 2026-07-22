<?php
// ai_review.php — লেভেল ৩: AI ইউজারের লেখা পড়ে ফিডব্যাক + স্কোর দেয়
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai_client.php';
$user = require_login();
$uid = $user['id'];
global $AI_PROVIDERS;

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$st = db()->prepare('SELECT w.*, c.name AS cat_name FROM writings w LEFT JOIN categories c ON c.id=w.category_id WHERE w.id=? AND w.user_id=?');
$st->execute([$id, $uid]);
$w = $st->fetch();
if (!$w) {
    set_flash('danger', 'লেখা পাওয়া যায়নি।');
    redirect('writings.php');
}

$settings = get_ai_settings($uid);
$providerModels = $AI_PROVIDERS[$settings['provider']]['models'] ?? [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $model = clean($_POST['model'] ?? '') ?: null;

    $system = "তুমি একজন অভিজ্ঞ বাংলা কনটেন্ট রাইটিং মেন্টর। "
        . "ইউজারের লেখা পড়ে সহজ, উৎসাহব্যঞ্জক বাংলায় গঠনমূলক ফিডব্যাক দাও।\n"
        . "এই কাঠামোতে উত্তর দাও:\n"
        . "✅ ভালো দিক: ...\n⚠️ দুর্বল দিক: ...\n💡 আরও ভালো করার উপায়: ...\n"
        . "সবশেষে আলাদা লাইনে অবশ্যই লিখবে ঠিক এই ফরম্যাটে: স্কোর: X/10 "
        . "(X হলো ১ থেকে ১০ এর মধ্যে একটি সংখ্যা)।";

    $catText = $w['cat_name'] ? "ক্যাটাগরি: {$w['cat_name']}\n" : '';
    $prompt = $catText . "শিরোনাম: {$w['title']}\n\nলেখা:\n{$w['body']}";

    $r = ai_chat($uid, $system, $prompt, $model);
    if ($r['ok']) {
        // স্কোর বের করা (বাংলা সংখ্যা → ইংরেজি করে)
        $text = $r['text'];
        $norm = strtr($text, ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9']);
        $score = null;
        if (preg_match('/স্কোর[:\s]*([0-9]{1,2})/u', $norm, $m)) {
            $score = min(10, max(0, (int)$m[1]));
        }
        // লেখায় সংরক্ষণ
        $save = db()->prepare('UPDATE writings SET ai_feedback=?, ai_score=? WHERE id=? AND user_id=?');
        $save->execute([$text, $score, $id, $uid]);
        set_flash('success', 'AI ফিডব্যাক সংরক্ষিত হয়েছে ✅');
        $result = ['ok' => true, 'text' => $text, 'score' => $score];
    } else {
        $result = ['ok' => false, 'error' => $r['error']];
    }
}

$pageTitle = 'AI যাচাই';
require __DIR__ . '/partials/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="writings.php">আমার লেখা</a></li>
    <li class="breadcrumb-item"><a href="writing_view.php?id=<?= (int)$w['id'] ?>"><?= e($w['title']) ?></a></li>
    <li class="breadcrumb-item active">AI যাচাই</li>
</ol></nav>

<h3 class="mb-3">🤖 AI যাচাই — "<?= e($w['title']) ?>"</h3>

<?php if (!ai_available($uid)): ?>
    <div class="alert alert-warning">AI ফিচার চালু নেই। আগে <a href="settings.php">সেটিংসে API key</a> দিন।</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-md-5">
            <div class="card p-4">
                <h6 class="card-title">তোমার লেখা</h6>
                <div class="example-box"><?= e($w['body']) ?></div>
                <form method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                    <label class="form-label">মডেল (ঐচ্ছিক ওভাররাইড)</label>
                    <select name="model" class="form-select mb-3">
                        <option value="">ডিফল্ট (<?= e($settings['default_model']) ?>)</option>
                        <?php foreach ($providerModels as $mo): ?>
                            <option value="<?= e($mo) ?>"><?= e($mo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-accent w-100" id="reviewBtn">🔍 যাচাই করো</button>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card p-4 h-100">
                <h6 class="card-title">AI ফিডব্যাক</h6>
                <?php if ($result === null): ?>
                    <p class="text-muted">"যাচাই করো" চাপলে AI এখানে ফিডব্যাক ও স্কোর দেবে।</p>
                <?php elseif (!$result['ok']): ?>
                    <div class="alert alert-danger"><?= e($result['error']) ?></div>
                <?php else: ?>
                    <?php if ($result['score'] !== null): ?>
                        <div class="mb-2"><span class="badge bg-success fs-6">স্কোর: <?= (int)$result['score'] ?>/১০</span></div>
                    <?php endif; ?>
                    <div style="white-space:pre-wrap;"><?= e($result['text']) ?></div>
                    <a href="writing_edit.php?id=<?= (int)$w['id'] ?>" class="btn btn-brand btn-sm mt-3">✏️ এই পরামর্শে লেখা ঠিক করো</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// লোডিং ইন্ডিকেটর
document.querySelector('form')?.addEventListener('submit', () => {
    const b = document.getElementById('reviewBtn');
    if (b) { b.disabled = true; b.textContent = '⏳ AI যাচাই করছে...'; }
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
