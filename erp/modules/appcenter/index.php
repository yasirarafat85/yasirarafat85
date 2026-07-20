<?php
/**
 * ===============================================================
 *  MODULE: App Center — সফটওয়্যার ডিপ্লয়মেন্ট ড্যাশবোর্ড
 * ===============================================================
 *  ইউজাররা এখান থেকে সুন্দর বাটনে ক্লিক করে অ্যাপ ইনস্টল/ডাউনলোড করবে।
 *  অ্যাপের path DB-তে থাকে, ফাইল থাকে নেটওয়ার্ক শেয়ারে।
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requirePermission('appcenter.view');

$pageTitle = 'App Center';
$db = Database::getInstance();

// module নিজের টেবিল তৈরি করে নেয় (প্রথমবার)
$db->query("CREATE TABLE IF NOT EXISTS apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    category VARCHAR(60) DEFAULT 'General',
    icon VARCHAR(60) NOT NULL DEFAULT 'bi-app',
    color VARCHAR(20) NOT NULL DEFAULT 'blue',
    network_path VARCHAR(500) NOT NULL,
    silent_args VARCHAR(255) DEFAULT NULL,
    version VARCHAR(40) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// সার্চ ও ক্যাটাগরি ফিল্টার
$q   = trim(input('q', ''));
$cat = trim(input('cat', ''));
$where = "is_active = 1";
$params = [];
if ($q !== '')   { $where .= " AND (name LIKE ? OR description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat !== '') { $where .= " AND category = ?"; $params[] = $cat; }

$apps = $db->fetchAll("SELECT * FROM apps WHERE $where ORDER BY sort_order, name", $params);
$categories = array_column($db->fetchAll("SELECT DISTINCT category FROM apps WHERE is_active = 1 ORDER BY category"), 'category');

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>App Center</h1><div class="sub">এক ক্লিকে সফটওয়্যার ইনস্টল বা ডাউনলোড করুন</div></div>
  <?php if (Auth::can('appcenter.manage')): ?>
    <a class="btn btn-ghost" href="<?= url('modules/appcenter/admin.php') ?>"><i class="bi bi-gear"></i> অ্যাপ ম্যানেজ</a>
  <?php endif; ?>
</div>

<!-- সার্চ + ফিল্টার -->
<form method="get" class="card-c" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:20px;padding:16px 18px">
  <div style="flex:1;min-width:200px;position:relative">
    <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted)"></i>
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="অ্যাপ খুঁজুন…" style="padding-left:40px">
  </div>
  <select class="form-control" name="cat" style="width:auto" onchange="this.form.submit()">
    <option value="">সব ক্যাটাগরি</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary" style="width:auto"><i class="bi bi-funnel"></i> ফিল্টার</button>
</form>

<?php if (!$apps): ?>
  <div class="card-c" style="text-align:center;padding:50px 20px;color:var(--text-muted)">
    <i class="bi bi-box2" style="font-size:46px"></i>
    <p style="margin-top:12px">কোনো অ্যাপ পাওয়া যায়নি।
      <?php if (Auth::can('appcenter.manage')): ?><br><a href="<?= url('modules/appcenter/admin.php') ?>" style="color:var(--primary);font-weight:600">অ্যাপ যোগ করুন →</a><?php endif; ?>
    </p>
  </div>
<?php else: ?>
  <div class="app-grid">
    <?php foreach ($apps as $app): $token = app_token((int)$app['id']); ?>
      <div class="app-card">
        <div class="app-top">
          <div class="app-icon <?= e($app['color']) ?>"><i class="bi <?= e($app['icon']) ?>"></i></div>
          <?php if ($app['version']): ?><span class="badge-c badge-gray">v<?= e($app['version']) ?></span><?php endif; ?>
        </div>
        <div class="app-name"><?= e($app['name']) ?></div>
        <div class="app-cat"><i class="bi bi-tag"></i> <?= e($app['category']) ?></div>
        <div class="app-desc"><?= e($app['description'] ?: 'কোনো বিবরণ নেই') ?></div>
        <div class="app-actions">
          <a class="btn btn-success btn-sm app-install"
             href="appdeploy://<?= (int)$app['id'] ?>?t=<?= $token ?>"
             data-name="<?= e($app['name']) ?>">
            <i class="bi bi-lightning-charge"></i> Install
          </a>
          <a class="btn btn-primary btn-sm" href="<?= url('modules/appcenter/download.php?id=' . (int)$app['id']) ?>">
            <i class="bi bi-download"></i> Download
          </a>
          <button type="button" class="btn btn-ghost btn-sm" title="নেটওয়ার্ক পাথ কপি"
                  onclick="copyPath(this, '<?= e(str_replace('\\', '\\\\', resolve_app_path($app['network_path']))) ?>')">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Install সাহায্য টোস্ট -->
<div id="installToast" class="install-toast">
  <i class="bi bi-info-circle"></i>
  <div>
    <b>Silent install শুরু হচ্ছে…</b><br>
    <small>কিছু না হলে এই পিসিতে helper সেটআপ করা নেই — "Download" ব্যবহার করুন অথবা IT-কে জানান।</small>
  </div>
  <button onclick="document.getElementById('installToast').classList.remove('show')" class="icon-btn" style="width:30px;height:30px"><i class="bi bi-x"></i></button>
</div>

<style>
.app-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:18px}
.app-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);
  padding:20px;box-shadow:var(--shadow-sm);transition:all .2s;display:flex;flex-direction:column}
.app-card:hover{box-shadow:var(--shadow);transform:translateY(-3px)}
.app-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.app-icon{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:28px}
.app-icon.blue{background:rgba(33,150,243,.12);color:#2196F3}
.app-icon.green{background:rgba(38,198,160,.12);color:#00897B}
.app-icon.orange{background:rgba(255,152,0,.12);color:#F57C00}
.app-icon.purple{background:rgba(171,71,188,.12);color:#8E24AA}
.app-icon.red{background:rgba(239,83,80,.12);color:#E53935}
.app-icon.gray{background:var(--bg-secondary);color:var(--text-muted)}
.app-name{font-family:'Plus Jakarta Sans';font-weight:700;font-size:17px}
.app-cat{font-size:12px;color:var(--text-muted);margin:3px 0 10px}
.app-desc{font-size:13px;color:var(--text-muted);line-height:1.5;flex:1;margin-bottom:16px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.app-actions{display:flex;gap:6px}
.app-actions .btn{flex:1;padding:8px 10px}
.app-actions .btn-ghost{flex:0 0 auto;width:auto;padding:8px 11px}
.install-toast{position:fixed;bottom:24px;right:24px;max-width:360px;background:var(--surface);
  border:1px solid var(--border);border-left:4px solid var(--info);border-radius:12px;padding:14px 16px;
  box-shadow:var(--shadow);display:none;align-items:flex-start;gap:12px;z-index:70}
.install-toast.show{display:flex;animation:slideIn .3s}
@keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
</style>

<script>
function copyPath(btn, path){
  navigator.clipboard.writeText(path).then(()=>{
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i>';
    setTimeout(()=>btn.innerHTML = old, 1200);
  });
}
// Install বাটনে ক্লিক করলে protocol চালু হয় + সাহায্য টোস্ট দেখায়
document.querySelectorAll('.app-install').forEach(a=>{
  a.addEventListener('click', ()=>{
    const t = document.getElementById('installToast');
    t.querySelector('b').textContent = a.dataset.name + ' — silent install শুরু হচ্ছে…';
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 7000);
  });
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
