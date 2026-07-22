<?php
// writing_edit.php — লেখা তৈরি ও সম্পাদনা (প্রি-ফিল সাপোর্ট সহ)
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$uid = $user['id'];

$cats = db()->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();

$id = 0;
$title = '';
$body = '';
$categoryId = (int)($_GET['category_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_save'])) {
    // ---- সংরক্ষণ ----
    verify_csrf();
    $id         = (int)($_POST['id'] ?? 0);
    $title      = clean($_POST['title'] ?? '');
    $body       = clean($_POST['body'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($title === '') $errors[] = 'শিরোনাম দিন।';
    if ($body === '')  $errors[] = 'লেখা খালি রাখা যাবে না।';

    if (!$errors) {
        $wc = count_words($body);
        // ক্যাটাগরি সত্যিই আছে কিনা যাচাই — না থাকলে null (FK এরর ঠেকাতে)
        $catVal = null;
        if ($categoryId) {
            $chk = db()->prepare('SELECT 1 FROM categories WHERE id = ?');
            $chk->execute([$categoryId]);
            if ($chk->fetch()) $catVal = $categoryId;
        }
        if ($id) {
            // মালিকানা যাচাই — শুধু নিজের লেখা এডিট করা যাবে
            $own = db()->prepare('SELECT user_id FROM writings WHERE id = ?');
            $own->execute([$id]);
            $row = $own->fetch();
            if (!$row || (int)$row['user_id'] !== $uid) {
                http_response_code(403);
                die('এই লেখা সম্পাদনার অনুমতি নেই।');
            }
            $up = db()->prepare('UPDATE writings SET title=?, body=?, word_count=?, category_id=?, updated_at=datetime(\'now\') WHERE id=? AND user_id=?');
            $up->execute([$title, $body, $wc, $catVal, $id, $uid]);
            set_flash('success', 'লেখা আপডেট হয়েছে ✅');
        } else {
            $ins = db()->prepare('INSERT INTO writings (user_id, category_id, title, body, word_count) VALUES (?,?,?,?,?)');
            $ins->execute([$uid, $catVal, $title, $body, $wc]);
            $id = (int)db()->lastInsertId();
            set_flash('success', 'নতুন লেখা সেভ হয়েছে ✅');
        }
        redirect('writing_view.php?id=' . $id);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prefill'])) {
    // ---- জেনারেটর থেকে প্রি-ফিল ----
    $title      = clean($_POST['title'] ?? '');
    $body       = clean($_POST['body'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
} elseif (($gid = (int)($_GET['id'] ?? 0)) > 0) {
    // ---- বিদ্যমান লেখা এডিট ----
    $st = db()->prepare('SELECT * FROM writings WHERE id = ? AND user_id = ?');
    $st->execute([$gid, $uid]);
    $w = $st->fetch();
    if (!$w) {
        set_flash('danger', 'লেখা পাওয়া যায়নি।');
        redirect('writings.php');
    }
    $id = (int)$w['id'];
    $title = $w['title'];
    $body = $w['body'];
    $categoryId = (int)$w['category_id'];
}

$pageTitle = $id ? 'লেখা সম্পাদনা' : 'নতুন লেখা';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-3"><?= $id ? '✏️ লেখা সম্পাদনা' : '✍️ নতুন লেখা' ?></h3>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card p-4">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="do_save" value="1">
                <div class="mb-3">
                    <label class="form-label">শিরোনাম</label>
                    <input type="text" name="title" class="form-control" value="<?= e($title) ?>" required>
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
                    <label class="form-label">লেখা</label>
                    <textarea name="body" id="body" class="form-control" rows="12" required><?= e($body) ?></textarea>
                    <small class="text-muted">শব্দ সংখ্যা: <span id="wc">0</span></small>
                </div>
                <button class="btn btn-brand">💾 সেভ করো</button>
                <a href="writings.php" class="btn btn-outline-secondary">বাতিল</a>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <h6 class="card-title">✅ সেল্‌ফ-চেক</h6>
            <p class="text-muted"><small>সেভের আগে নিজেকে জিজ্ঞেস করো:</small></p>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox"><label class="form-check-label">প্রথম লাইনে কি হুক আছে?</label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox"><label class="form-check-label">বাক্য কি ছোট ও সহজ?</label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox"><label class="form-check-label">একটাই মূল বার্তা আছে তো?</label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox"><label class="form-check-label">"দেখাও, বলো না" মেনেছি?</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label">শেষে কি স্পষ্ট পদক্ষেপ আছে?</label></div>
        </div>
    </div>
</div>

<script>
// লাইভ শব্দ গণনা
const bodyEl = document.getElementById('body');
const wcEl = document.getElementById('wc');
function updateWC() {
    const t = bodyEl.value.trim();
    wcEl.textContent = t === '' ? 0 : t.split(/\s+/).length;
}
bodyEl.addEventListener('input', updateWC);
updateWC();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
