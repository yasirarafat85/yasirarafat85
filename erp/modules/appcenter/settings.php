<?php
/**
 * settings.php — App Center সেটিংস (আপলোড/শেয়ার বেস পাথ)
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/_schema.php';
Auth::requirePermission('appcenter.manage');

$pageTitle = 'App Center Settings';
$db = Database::getInstance();
appcenter_ensure_schema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $path = rtrim(trim(input('share_path')), '\\/');
    if ($path === '') {
        flash('error', 'শেয়ার পাথ খালি রাখা যাবে না।');
    } else {
        setting_set($db, 'appcenter_share_path', $path);
        flash('success', 'শেয়ার পাথ সংরক্ষিত হয়েছে।');
    }
    redirect('settings.php');
}

$sharePath = appcenter_share_path($db);

// পাথটি লেখা যায় কিনা পরীক্ষা (আপলোড কাজ করবে কিনা বোঝাতে)
$writable = is_dir($sharePath) && is_writable($sharePath);
$exists   = is_dir($sharePath);

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>App Center Settings</h1><div class="sub">আপলোড করা ইনস্টলার যেখানে জমা হবে সেই নেটওয়ার্ক শেয়ার</div></div>
  <a class="btn btn-ghost" href="<?= url('modules/appcenter/admin.php') ?>"><i class="bi bi-hdd-stack"></i> App ম্যানেজ</a>
</div>

<div class="card-c" style="max-width:720px">
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">শেয়ার / আপলোড বেস পাথ</label>
      <input class="form-control" name="share_path" value="<?= e($sharePath) ?>"
             placeholder="\\hardware_monitor\appcenter" style="font-family:monospace">
      <p style="font-size:12.5px;color:var(--text-muted);margin:8px 0 0">
        অ্যাপ যোগ করার সময় আপলোড করা <code>.exe/.msi</code> ফাইল এই ফোল্ডারে জমা হবে,
        আর ক্লায়েন্ট পিসি এখান থেকেই ইনস্টল/ডাউনলোড করবে।
        উদাহরণ: <code>\\hardware_monitor\appcenter</code>
      </p>
    </div>

    <!-- পাথের অবস্থা -->
    <div class="alert <?= $writable ? 'alert-success' : 'alert-danger' ?>" style="margin-top:6px">
      <?php if ($writable): ?>
        <i class="bi bi-check-circle"></i> পাথটি পাওয়া গেছে এবং **লেখা যাচ্ছে** — আপলোড কাজ করবে।
      <?php elseif ($exists): ?>
        <i class="bi bi-exclamation-triangle"></i> পাথটি আছে কিন্তু **লেখা যাচ্ছে না**।
        শেয়ারে Apache/PHP-র write অ্যাক্সেস দিন (everyone: change)।
      <?php else: ?>
        <i class="bi bi-exclamation-triangle"></i> পাথটি সার্ভার থেকে **পাওয়া যাচ্ছে না**।
        শেয়ার আছে কিনা ও নাম ঠিক আছে কিনা দেখুন।
      <?php endif; ?>
    </div>

    <button class="btn btn-success" style="margin-top:8px"><i class="bi bi-check-lg"></i> সংরক্ষণ করুন</button>
  </form>
</div>

<div class="card-c" style="max-width:720px;margin-top:18px">
  <h3 style="font-size:15px;margin-bottom:10px"><i class="bi bi-info-circle"></i> কীভাবে কাজ করে</h3>
  <ul style="color:var(--text-muted);font-size:14px;line-height:1.9;margin:0;padding-left:18px">
    <li>এই পাথে একটা নেটওয়ার্ক ফোল্ডার শেয়ার করুন (everyone: read + change)।</li>
    <li>অ্যাপ যোগ করার সময় শুধু ইনস্টলার ফাইল <b>আপলোড</b> করবেন — path নিজে থেকে বসবে।</li>
    <li>পাথ বদলালে শুধু এখানে আপডেট করবেন — নতুন অ্যাপ নতুন পাথে যাবে।</li>
    <li>বড় ইনস্টলার আপলোডে সমস্যা হলে সার্ভারের <code>php.ini</code>-তে
        <code>upload_max_filesize</code> ও <code>post_max_size</code> বাড়ান (যেমন 512M)।</li>
  </ul>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
