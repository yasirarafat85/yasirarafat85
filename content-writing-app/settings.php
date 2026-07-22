<?php
// settings.php — AI প্রোভাইডার, API key ও মডেল কনফিগার (লেভেল ৩ সেটআপ)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai_client.php';
$user = require_login();
$uid = $user['id'];

global $AI_PROVIDERS;

$current = get_ai_settings($uid);
$hasKey  = $current['api_key'] !== '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['do_test'])) {
        // ---- কানেকশন টেস্ট (সংরক্ষিত সেটিংস দিয়ে) ----
        $r = ai_chat($uid, 'তুমি একজন সহকারী। সংক্ষেপে বাংলায় উত্তর দাও।', 'শুধু লেখো: "কানেকশন সফল ✅"');
        $testResult = $r;
    } else {
        // ---- সেটিংস সংরক্ষণ ----
        $provider = clean($_POST['provider'] ?? '');
        $model    = clean($_POST['default_model'] ?? '');
        $keyInput = trim($_POST['api_key'] ?? '');

        $errors = [];
        if (!isset($AI_PROVIDERS[$provider])) {
            $errors[] = 'অবৈধ প্রোভাইডার।';
        } elseif (!in_array($model, $AI_PROVIDERS[$provider]['models'], true)) {
            $errors[] = 'এই প্রোভাইডারের জন্য অবৈধ মডেল।';
        }

        if (!$errors) {
            // key ফাঁকা দিলে আগেরটা রেখে দাও; নতুন দিলে এনক্রিপ্ট করো
            if ($keyInput !== '') {
                $encKey = encrypt_key($keyInput);
            } else {
                $st = db()->prepare('SELECT api_key FROM settings WHERE user_id = ?');
                $st->execute([$uid]);
                $encKey = $st->fetch()['api_key'] ?? '';
            }

            $up = db()->prepare("
                INSERT INTO settings (user_id, provider, api_key, default_model)
                VALUES (:uid, :provider, :key, :model)
                ON CONFLICT(user_id) DO UPDATE SET
                    provider = :provider, api_key = :key, default_model = :model
            ");
            $up->execute([':uid' => $uid, ':provider' => $provider, ':key' => $encKey, ':model' => $model]);
            set_flash('success', 'AI সেটিংস সংরক্ষিত হয়েছে ✅');
            redirect('settings.php');
        } else {
            foreach ($errors as $er) set_flash('danger', $er);
        }
        $current = get_ai_settings($uid);
        $hasKey  = $current['api_key'] !== '';
    }
}

$pageTitle = 'AI সেটিংস';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-1">🤖 AI সেটিংস — লেভেল ৩</h3>
<p class="text-muted">প্রোভাইডার ও মডেল বেছে API key দিন। key না দিলে AI ফিচার বন্ধ থাকবে, তবে লেভেল ১+২ চলবে।</p>

<?php if ($testResult !== null): ?>
    <div class="alert alert-<?= $testResult['ok'] ? 'success' : 'danger' ?>">
        <strong>টেস্ট ফলাফল:</strong>
        <?= $testResult['ok'] ? e($testResult['text']) : e($testResult['error']) ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card p-4">
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">প্রোভাইডার</label>
                    <select name="provider" id="provider" class="form-select">
                        <?php foreach ($AI_PROVIDERS as $slug => $p): ?>
                            <option value="<?= e($slug) ?>" <?= $current['provider'] === $slug ? 'selected' : '' ?>><?= e($p['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">ডিফল্ট মডেল</label>
                    <select name="default_model" id="default_model" class="form-select"></select>
                    <small class="text-muted">নতুন AI কল এই মডেল ব্যবহার করবে (কল করার সময় বদলানোও যাবে)।</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">API Key</label>
                    <input type="password" name="api_key" class="form-control" placeholder="<?= $hasKey ? 'সংরক্ষিত: ' . e(mask_key($current['api_key'])) . ' (বদলাতে নতুন key দিন)' : 'sk-... এখানে key বসান' ?>">
                    <small class="text-muted">key এনক্রিপ্ট করে সংরক্ষণ করা হয়, কখনো পুরোটা দেখানো হয় না।</small>
                </div>
                <button class="btn btn-brand">💾 সংরক্ষণ করো</button>
                <?php if ($hasKey): ?>
                    <button name="do_test" value="1" class="btn btn-outline-secondary">🔌 কানেকশন টেস্ট</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <h6 class="card-title">অবস্থা</h6>
            <p class="mb-1">AI ফিচার:
                <?php if ($hasKey): ?><span class="badge bg-success">চালু ✅</span>
                <?php else: ?><span class="badge bg-secondary">বন্ধ</span><?php endif; ?>
            </p>
            <p class="mb-1"><small>প্রোভাইডার: <strong><?= e($AI_PROVIDERS[$current['provider']]['label'] ?? $current['provider']) ?></strong></small></p>
            <p class="mb-0"><small>মডেল: <strong><?= e($current['default_model']) ?></strong></small></p>
            <hr>
            <p class="text-muted mb-0"><small>key কোথায় পাবে? OpenRouter → openrouter.ai/keys, OpenAI → platform.openai.com, Anthropic → console.anthropic.com</small></p>
        </div>
    </div>
</div>

<script>
// প্রোভাইডার অনুযায়ী মডেল তালিকা বদলাবে
const PROVIDERS = <?= json_encode($AI_PROVIDERS, JSON_UNESCAPED_UNICODE) ?>;
const currentModel = <?= json_encode($current['default_model']) ?>;
const provSel = document.getElementById('provider');
const modelSel = document.getElementById('default_model');

function fillModels() {
    const models = (PROVIDERS[provSel.value] || {}).models || [];
    modelSel.innerHTML = '';
    models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m; opt.textContent = m;
        if (m === currentModel) opt.selected = true;
        modelSel.appendChild(opt);
    });
}
provSel.addEventListener('change', fillModels);
fillModels();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
