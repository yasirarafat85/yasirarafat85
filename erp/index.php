<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requirePermission('dashboard.view');

$pageTitle = 'Dashboard';
$db = Database::getInstance();

// পরিসংখ্যান
$totalUsers  = (int) $db->scalar("SELECT COUNT(*) FROM users");
$activeUsers = (int) $db->scalar("SELECT COUNT(*) FROM users WHERE status = 1");
$totalRoles  = (int) $db->scalar("SELECT COUNT(*) FROM roles");
$totalItems  = 0;
// inventory টেবিল থাকলে গণনা (module ইনস্টল করা থাকলে)
try { $totalItems = (int) $db->scalar("SELECT COUNT(*) FROM inventory_items"); } catch (Throwable $e) {}

$recentUsers = $db->fetchAll(
    "SELECT u.name, u.email, u.created_at, r.name AS role_name
     FROM users u LEFT JOIN roles r ON r.id = u.role_id
     ORDER BY u.id DESC LIMIT 6"
);

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>স্বাগতম, <?= e(explode(' ', $__user['name'])[0]) ?> 👋</h1>
    <div class="sub">আজ <?= date('d M Y') ?> — আপনার সিস্টেমের সারসংক্ষেপ</div>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:22px">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="bi bi-people"></i></div>
    <div><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">মোট ইউজার</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-person-check"></i></div>
    <div><div class="stat-value" style="color:#00897B"><?= $activeUsers ?></div><div class="stat-label">সক্রিয় ইউজার</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="bi bi-shield-lock"></i></div>
    <div><div class="stat-value" style="color:#8E24AA"><?= $totalRoles ?></div><div class="stat-label">Role সংখ্যা</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="bi bi-box-seam"></i></div>
    <div><div class="stat-value" style="color:#F57C00"><?= $totalItems ?></div><div class="stat-label">Inventory Item</div></div>
  </div>
</div>

<div class="card-c tight">
  <div class="card-head"><h3>সাম্প্রতিক ইউজার</h3>
    <?php if (Auth::can('users.view')): ?>
      <a class="btn btn-ghost btn-sm" href="<?= url('users.php') ?>">সব দেখুন</a>
    <?php endif; ?>
  </div>
  <table class="table-c">
    <thead><tr><th>নাম</th><th>ইমেইল</th><th>Role</th><th>যোগদান</th></tr></thead>
    <tbody>
      <?php foreach ($recentUsers as $u): ?>
        <tr>
          <td style="font-weight:600"><?= e($u['name']) ?></td>
          <td style="color:var(--text-muted)"><?= e($u['email']) ?></td>
          <td><span class="badge-c badge-blue"><?= e($u['role_name'] ?? '—') ?></span></td>
          <td style="color:var(--text-muted)"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentUsers): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:26px">কোনো ইউজার নেই</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
