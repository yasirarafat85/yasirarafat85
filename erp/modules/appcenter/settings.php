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
        setting_set($db, 'appcenter_guest',           input('guest')     ? '1' : '0');
        setting_set($db, 'appcenter_guest_install',   input('g_install') ? '1' : '0');
        setting_set($db, 'appcenter_guest_update',    input('g_update')  ? '1' : '0');
        setting_set($db, 'appcenter_guest_uninstall', input('g_uninstall') ? '1' : '0');
        setting_set($db, 'appcenter_guest_download',  input('g_download') ? '1' : '0');
        flash('success', 'সেটিংস সংরক্ষিত হয়েছে।');
    }
    redirect('modules/appcenter/settings.php');
}

$sharePath = appcenter_share_path($db);
$guestOn   = setting_get($db, 'appcenter_guest', '0') === '1';
$gAllow = [
    'install'   => setting_get($db, 'appcenter_guest_install',   '1') === '1',
    'update'    => setting_get($db, 'appcenter_guest_update',    '0') === '1',
    'uninstall' => setting_get($db, 'appcenter_guest_uninstall', '0') === '1',
    'download'  => setting_get($db, 'appcenter_guest_download',  '1') === '1',
];

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

    <!-- Guest mode -->
    <div class="form-group" style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border)">
      <label class="check" style="align-items:flex-start">
        <input type="checkbox" name="guest" value="1" <?= $guestOn ? 'checked' : '' ?> style="margin-top:3px">
        <span>
          <b>Guest mode চালু করুন</b><br>
          <small style="color:var(--text-muted)">চালু থাকলে ইউজাররা <b>লগইন ছাড়াই</b> App Center পেজ দেখে
          কাজ করতে পারবে — কিন্তু অ্যাপ manage/এডিট করতে পারবে না।
          বন্ধ থাকলে লগইন লাগবে।</small>
        </span>
      </label>

      <!-- guest কী কী করতে পারবে -->
      <div style="margin-top:14px;padding:14px 16px;border:1px solid var(--border);border-radius:12px;background:var(--bg-secondary)">
        <div style="font-weight:600;font-size:14px;margin-bottom:10px">Guest কী কী করতে পারবে?</div>
        <div class="checkbox-grid">
          <label class="check"><input type="checkbox" name="g_install"   value="1" <?= $gAllow['install']   ? 'checked' : '' ?>> <i class="bi bi-lightning-charge" style="color:#00897B"></i> Install</label>
          <label class="check"><input type="checkbox" name="g_update"    value="1" <?= $gAllow['update']    ? 'checked' : '' ?>> <i class="bi bi-arrow-repeat" style="color:#F57C00"></i> Update</label>
          <label class="check"><input type="checkbox" name="g_uninstall" value="1" <?= $gAllow['uninstall'] ? 'checked' : '' ?>> <i class="bi bi-trash3" style="color:#E53935"></i> Uninstall</label>
          <label class="check"><input type="checkbox" name="g_download"  value="1" <?= $gAllow['download']  ? 'checked' : '' ?>> <i class="bi bi-download" style="color:#2196F3"></i> Download</label>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin:10px 0 0">টিক দেওয়া কাজগুলোই guest পেজে বাটন হিসেবে দেখাবে। (লগইন করা অ্যাডমিন সবসময় সব দেখবে।)</p>
      </div>
    </div>

    <button class="btn btn-success" style="margin-top:8px"><i class="bi bi-check-lg"></i> সংরক্ষণ করুন</button>
  </form>
</div>

<?php if ($guestOn): ?>
<div class="card-c" style="max-width:720px;margin-top:14px;background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.3)">
  <div style="font-size:14px">✅ Guest link (লগইন ছাড়া): <br>
    <code style="font-size:12.5px"><?= e(rtrim((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].BASE_URL,'/')) ?>/modules/appcenter/index.php</code>
  </div>
</div>
<?php endif; ?>

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
