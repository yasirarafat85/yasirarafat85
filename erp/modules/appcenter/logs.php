<?php
/**
 * logs.php — Install Log: কোন পিসি থেকে কোন অ্যাপ কখন ইনস্টল/ডাউনলোড হলো।
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/_schema.php';
Auth::requirePermission('appcenter.manage');

$pageTitle = 'Install Logs';
$db = Database::getInstance();
appcenter_ensure_schema($db);

// পুরনো লগ মুছে ফেলা
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (input('action') === 'clear') {
        $db->query("DELETE FROM install_logs");
        flash('success', 'সব লগ মুছে ফেলা হয়েছে।');
    }
    redirect('logs.php');
}

// ফিল্টার
$fStatus = trim(input('status', ''));
$fMethod = trim(input('method', ''));
$fq      = trim(input('q', ''));
$where = '1=1'; $params = [];
if ($fStatus !== '') { $where .= ' AND status = ?';  $params[] = $fStatus; }
if ($fMethod !== '') { $where .= ' AND method = ?';  $params[] = $fMethod; }
if ($fq !== '')      { $where .= ' AND (app_name LIKE ? OR pc_name LIKE ? OR user_name LIKE ?)';
                       $params[] = "%$fq%"; $params[] = "%$fq%"; $params[] = "%$fq%"; }

$logs = $db->fetchAll("SELECT * FROM install_logs WHERE $where ORDER BY id DESC LIMIT 300", $params);

// পরিসংখ্যান
$total   = (int) $db->scalar("SELECT COUNT(*) FROM install_logs");
$success = (int) $db->scalar("SELECT COUNT(*) FROM install_logs WHERE status = 'success'");
$failed  = (int) $db->scalar("SELECT COUNT(*) FROM install_logs WHERE status = 'failed'");
$pcs     = (int) $db->scalar("SELECT COUNT(DISTINCT pc_name) FROM install_logs WHERE pc_name IS NOT NULL");

function status_badge($s) {
    return match ($s) {
        'success' => '<span class="badge-c badge-green"><i class="bi bi-check-circle"></i> সফল</span>',
        'failed'  => '<span class="badge-c badge-red"><i class="bi bi-x-circle"></i> ব্যর্থ</span>',
        default   => '<span class="badge-c badge-gray"><i class="bi bi-hourglass-split"></i> শুরু</span>',
    };
}
function method_badge($m) {
    return match ($m) {
        'install'   => '<span class="badge-c badge-blue"><i class="bi bi-lightning-charge"></i> Install</span>',
        'update'    => '<span class="badge-c" style="background:rgba(255,152,0,.15);color:#F57C00"><i class="bi bi-arrow-repeat"></i> Update</span>',
        'uninstall' => '<span class="badge-c badge-red"><i class="bi bi-trash3"></i> Uninstall</span>',
        'download'  => '<span class="badge-c badge-gray"><i class="bi bi-download"></i> Download</span>',
        default     => '<span class="badge-c badge-gray"><i class="bi bi-lightning-charge"></i> ' . e(ucfirst($m)) . '</span>',
    };
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>Install Logs</h1><div class="sub">কোন পিসি থেকে কোন অ্যাপ ইনস্টল/ডাউনলোড হলো</div></div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= url('modules/appcenter/admin.php') ?>"><i class="bi bi-hdd-stack"></i> App ম্যানেজ</a>
    <?php if ($total): ?>
    <form method="post" onsubmit="return confirm('সব লগ মুছে ফেলবেন?')">
      <?= csrf_field() ?><input type="hidden" name="action" value="clear">
      <button class="btn btn-danger"><i class="bi bi-trash"></i> লগ পরিষ্কার</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-list-check"></i></div>
    <div><div class="stat-value"><?= $total ?></div><div class="stat-label">মোট ইভেন্ট</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
    <div><div class="stat-value" style="color:#00897B"><?= $success ?></div><div class="stat-label">সফল</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="bi bi-x-circle"></i></div>
    <div><div class="stat-value" style="color:#E53935"><?= $failed ?></div><div class="stat-label">ব্যর্থ</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="bi bi-pc-display"></i></div>
    <div><div class="stat-value" style="color:#8E24AA"><?= $pcs ?></div><div class="stat-label">পিসি সংখ্যা</div></div></div>
</div>

<form method="get" class="card-c" style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:20px;padding:15px 17px">
  <div style="flex:1;min-width:180px;position:relative">
    <i class="bi bi-search" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted)"></i>
    <input class="form-control" name="q" value="<?= e($fq) ?>" placeholder="অ্যাপ / পিসি / ইউজার…" style="padding-left:38px">
  </div>
  <select class="form-control" name="method" style="width:auto">
    <option value="">সব পদ্ধতি</option>
    <option value="install"   <?= $fMethod==='install'?'selected':'' ?>>Install</option>
    <option value="update"    <?= $fMethod==='update'?'selected':'' ?>>Update</option>
    <option value="uninstall" <?= $fMethod==='uninstall'?'selected':'' ?>>Uninstall</option>
    <option value="download"  <?= $fMethod==='download'?'selected':'' ?>>Download</option>
  </select>
  <select class="form-control" name="status" style="width:auto">
    <option value="">সব স্ট্যাটাস</option>
    <option value="success" <?= $fStatus==='success'?'selected':'' ?>>সফল</option>
    <option value="failed"  <?= $fStatus==='failed'?'selected':'' ?>>ব্যর্থ</option>
    <option value="started" <?= $fStatus==='started'?'selected':'' ?>>শুরু</option>
  </select>
  <button class="btn btn-primary" style="width:auto"><i class="bi bi-funnel"></i> ফিল্টার</button>
</form>

<div class="card-c tight">
  <table class="table-c">
    <thead><tr><th>সময়</th><th>অ্যাপ</th><th>পদ্ধতি</th><th>পিসি</th><th>ইউজার / IP</th><th>স্ট্যাটাস</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td style="color:var(--text-muted);white-space:nowrap"><?= e(date('d M, H:i', strtotime($l['created_at']))) ?></td>
          <td style="font-weight:600"><?= e($l['app_name'] ?: '—') ?></td>
          <td><?= method_badge($l['method']) ?></td>
          <td><?= $l['pc_name'] ? '<span style="font-weight:600"><i class="bi bi-pc-display" style="color:var(--text-muted)"></i> '.e($l['pc_name']).'</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
          <td style="color:var(--text-muted)">
            <?= $l['user_name'] ? e($l['user_name']) : '' ?>
            <?php if ($l['ip_address']): ?><br><small><?= e($l['ip_address']) ?></small><?php endif; ?>
          </td>
          <td><?= status_badge($l['status']) ?><?php if ($l['note']): ?><br><small style="color:var(--text-muted)"><?= e($l['note']) ?></small><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:34px">
          কোনো লগ নেই। কেউ Install/Download করলে এখানে দেখা যাবে।
        </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12.5px;color:var(--text-muted);margin-top:14px">
  <i class="bi bi-info-circle"></i> Silent install-এ পিসির নাম helper থেকে আসে।
  Download-এ ব্রাউজার হোস্টনেম দেয় না, তাই ইউজার ও IP রাখা হয়। সর্বশেষ ৩০০টি ইভেন্ট দেখানো হচ্ছে।
</p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
