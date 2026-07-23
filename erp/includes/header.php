<?php
/**
 * includes/header.php — প্রতিটি ভেতরের পেজের উপরের অংশ।
 * ব্যবহার:  $pageTitle = 'Dashboard'; require '.../includes/header.php';
 * সাইডবার মেনু ডাটাবেস থেকে আসে এবং ইউজারের permission অনুযায়ী দেখানো হয়।
 */
Auth::requireLogin();
$__user = Auth::user();
$db     = Database::getInstance();

/** মেনু গাছ (parent → children) তৈরি, permission ফিল্টারসহ */
function build_menu(): array
{
    $rows = Database::getInstance()->fetchAll(
        "SELECT * FROM menus WHERE is_active = 1 ORDER BY sort_order, id"
    );
    $tree = [];
    foreach ($rows as $r) {
        if ($r['parent_id'] === null) $tree[$r['id']] = $r + ['children' => []];
    }
    foreach ($rows as $r) {
        if ($r['parent_id'] !== null && isset($tree[$r['parent_id']])) {
            $tree[$r['parent_id']]['children'][] = $r;
        }
    }
    return $tree;
}

/** এই মেনু আইটেম ইউজার দেখতে পারবে কিনা */
function menu_visible(array $item): bool
{
    if (empty($item['permission'])) return true;   // permission না থাকলে সবাই দেখবে
    return Auth::can($item['permission']);
}
$menuTree = build_menu();
$curr = current_page();
?>
<!DOCTYPE html>
<html lang="bn" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<div class="app">
  <!-- ================= SIDEBAR ================= -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo"><?= strtoupper(substr(APP_NAME, 0, 1)) ?></div>
      <div class="name"><?= APP_NAME ?></div>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($menuTree as $item): ?>
        <?php if (!menu_visible($item)) continue; ?>
        <?php
          // দৃশ্যমান শিশু-মেনু ফিল্টার
          $kids = array_filter($item['children'], 'menu_visible');
        ?>
        <?php if (empty($item['children'])): ?>
          <?php $isActive = ($curr === basename($item['url'])); ?>
          <a class="nav-item <?= $isActive ? 'active' : '' ?>" href="<?= url($item['url']) ?>">
            <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['title']) ?></span>
          </a>
        <?php elseif (!empty($kids)): ?>
          <div class="nav-group-label"><?= e($item['title']) ?></div>
          <div class="nav-sub">
            <?php foreach ($kids as $child): ?>
              <?php $isActive = ($curr === basename($child['url'])); ?>
              <a class="nav-item <?= $isActive ? 'active' : '' ?>" href="<?= url($child['url']) ?>">
                <i class="bi <?= e($child['icon']) ?>"></i><span><?= e($child['title']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </aside>

  <!-- ================= MAIN ================= -->
  <div class="main">
    <header class="topbar">
      <div class="left">
        <button class="icon-btn" id="menu-toggle" aria-label="Menu"><i class="bi bi-list"></i></button>
        <h2><?= isset($pageTitle) ? e($pageTitle) : APP_NAME ?></h2>
      </div>
      <div class="left" style="gap:12px">
        <button class="icon-btn" id="theme-toggle" aria-label="Theme"><i class="bi bi-moon-stars"></i></button>
        <div class="user-chip" id="user-chip">
          <div class="avatar"><?= strtoupper(substr($__user['name'], 0, 1)) ?></div>
          <div class="info">
            <div style="font-weight:600;font-size:14px"><?= e($__user['name']) ?></div>
            <small><?= e($__user['role_name'] ?? 'User') ?></small>
          </div>
          <i class="bi bi-chevron-down" style="font-size:12px;color:var(--text-muted)"></i>
          <div class="dropdown-menu-c" id="user-dropdown">
            <a href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right"></i> লগআউট</a>
          </div>
        </div>
      </div>
    </header>
    <main class="content">
      <?php if ($m = get_flash('success')): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i><?= e($m) ?></div>
      <?php endif; ?>
      <?php if ($m = get_flash('error')): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i><?= e($m) ?></div>
      <?php endif; ?>
