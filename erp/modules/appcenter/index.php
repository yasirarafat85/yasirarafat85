<?php
/**
 * ===============================================================
 *  MODULE: App Center — সফটওয়্যার ডিপ্লয়মেন্ট ড্যাশবোর্ড
 * ===============================================================
 *  ইউজাররা এখান থেকে সুন্দর বাটনে ক্লিক করে অ্যাপ ইনস্টল/ডাউনলোড করবে।
 *  অ্যাপের path DB-তে থাকে, ফাইল থাকে নেটওয়ার্ক শেয়ারে।
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/_schema.php';

$pageTitle = 'App Center';
$db = Database::getInstance();
appcenter_ensure_schema($db);

/**
 * Guest mode — Settings-এ চালু থাকলে লগইন ছাড়াই App Center দেখা ও
 * Install/Download করা যায়, তবে manage করা যায় না।
 */
$guest = false;
if (Auth::check()) {
    Auth::requirePermission('appcenter.view');
} elseif (setting_get($db, 'appcenter_guest', '0') === '1') {
    $guest = true;
} else {
    redirect('auth/login.php');
}

// সার্চ ও ক্যাটাগরি ফিল্টার
$q   = trim(input('q', ''));
$cat = trim(input('cat', ''));
$where = "is_active = 1";
$params = [];
if ($q !== '')   { $where .= " AND (name LIKE ? OR description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat !== '') { $where .= " AND category = ?"; $params[] = $cat; }

$apps = $db->fetchAll("SELECT * FROM apps WHERE $where ORDER BY sort_order, name", $params);
$categories = array_column($db->fetchAll("SELECT DISTINCT category FROM apps WHERE is_active = 1 ORDER BY category"), 'category');

if (!$guest) {
    require __DIR__ . '/../../includes/header.php';
} else {
    // ---- লগইন ছাড়া guest layout (সাইডবার নেই) ----
    ?><!DOCTYPE html>
<html lang="bn" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>App Center — <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body style="background:var(--bg-secondary)">
  <header class="topbar" style="position:sticky;top:0;z-index:30">
    <div class="left" style="display:flex;align-items:center;gap:11px">
      <div class="logo" style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800"><?= strtoupper(substr(APP_NAME,0,1)) ?></div>
      <h2 style="font-size:18px">App Center</h2>
    </div>
    <div class="left" style="gap:12px;display:flex;align-items:center">
      <button class="icon-btn" id="theme-toggle" aria-label="Theme"><i class="bi bi-moon-stars"></i></button>
      <a class="btn btn-ghost btn-sm" href="<?= url('auth/login.php') ?>"><i class="bi bi-box-arrow-in-right"></i> অ্যাডমিন লগইন</a>
    </div>
  </header>
  <main class="content" style="max-width:1180px;margin:0 auto;padding:28px 26px">
<?php }
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
  <div class="app-grid">
    <?php foreach ($apps as $app):
      $token = app_token((int)$app['id']);
      $isWinget = ($app['install_type'] === 'winget');
      $allow = appcenter_allowed_actions($db, $app, $guest);   // per-app guest permission
    ?>
      <div class="app-card">
        <div class="app-top">
          <div class="app-icon <?= e($app['color']) ?>"><i class="bi <?= e($app['icon']) ?>"></i></div>
          <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end">
            <?php if ($isWinget): ?><span class="badge-c badge-blue" title="winget (Windows Package Manager)"><i class="bi bi-box"></i></span><?php endif; ?>
            <?php if ($app['version']): ?><span class="badge-c badge-gray">v<?= e($app['version']) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="app-name"><?= e($app['name']) ?></div>
        <div class="app-cat"><i class="bi bi-tag"></i> <?= e($app['category']) ?></div>
        <div class="app-desc"><?= e($app['description'] ?: 'কোনো বিবরণ নেই') ?></div>
        <div class="app-actions">
          <?php if ($allow['install']): ?>
          <a class="btn btn-success btn-sm app-install"
             href="appdeploy://<?= (int)$app['id'] ?>?t=<?= $token ?>&a=install"
             data-name="<?= e($app['name']) ?>" data-action="Install">
            <i class="bi bi-lightning-charge"></i> Install
          </a>
          <?php endif; ?>
          <?php if ($isWinget): ?>
            <?php if ($allow['update']): ?>
            <a class="btn btn-warning btn-sm app-install"
               href="appdeploy://<?= (int)$app['id'] ?>?t=<?= $token ?>&a=update"
               data-name="<?= e($app['name']) ?>" data-action="Update">
              <i class="bi bi-arrow-repeat"></i> Update
            </a>
            <?php endif; ?>
            <?php if ($allow['uninstall']): ?>
            <a class="btn btn-danger btn-sm app-install"
               href="appdeploy://<?= (int)$app['id'] ?>?t=<?= $token ?>&a=uninstall"
               data-name="<?= e($app['name']) ?>" data-action="Uninstall">
              <i class="bi bi-trash3"></i> Uninstall
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-ghost btn-sm" title="winget ID কপি"
                    onclick="copyPath(this, '<?= e($app['winget_id']) ?>')">
              <i class="bi bi-clipboard"></i> ID
            </button>
          <?php else: ?>
            <?php if ($allow['download']): ?>
            <a class="btn btn-primary btn-sm" href="<?= url('modules/appcenter/download.php?id=' . (int)$app['id']) ?>">
              <i class="bi bi-download"></i> Download
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-ghost btn-sm" title="নেটওয়ার্ক পাথ কপি"
                    onclick="copyPath(this, '<?= e(str_replace('\\', '\\\\', resolve_app_path($app['network_path'] ?? ''))) ?>')">
              <i class="bi bi-clipboard"></i>
            </button>
          <?php endif; ?>
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
.app-actions{display:flex;gap:6px;flex-wrap:wrap}
.app-actions .btn{flex:1 1 auto;min-width:86px;padding:8px 10px}
.app-actions .btn-ghost{flex:0 0 auto;min-width:0;width:auto;padding:8px 11px}
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
// Install/Update/Uninstall বাটনে ক্লিক করলে protocol চালু হয় + সাহায্য টোস্ট
const actionBn = {Install:'ইনস্টল', Update:'আপডেট', Uninstall:'আনইনস্টল'};
document.querySelectorAll('.app-install').forEach(a=>{
  a.addEventListener('click', (e)=>{
    // আনইনস্টলে আগে নিশ্চিত করি
    if (a.dataset.action === 'Uninstall' && !confirm(a.dataset.name + ' আনইনস্টল করবেন?')) {
      e.preventDefault();
      return;
    }
    const t = document.getElementById('installToast');
    const act = actionBn[a.dataset.action] || 'ইনস্টল';
    t.querySelector('b').textContent = a.dataset.name + ' — ' + act + ' শুরু হচ্ছে…';
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 7000);
  });
});
</script>
<?php if (!$guest): ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
<?php else: ?>
  </main>
  <script>
    // guest layout — শুধু থিম টগল
    const html = document.documentElement, tt = document.getElementById('theme-toggle');
    if (localStorage.getItem('theme') === 'dark' ||
       (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches)) html.setAttribute('data-theme','dark');
    function syncIcon(){ tt.querySelector('i').className = html.getAttribute('data-theme')==='dark' ? 'bi bi-sun' : 'bi bi-moon-stars'; }
    syncIcon();
    tt?.addEventListener('click', () => {
      const dark = html.getAttribute('data-theme') === 'dark';
      html.setAttribute('data-theme', dark ? 'light' : 'dark');
      localStorage.setItem('theme', dark ? 'light' : 'dark'); syncIcon();
    });
  </script>
</body>
</html>
<?php endif; ?>
