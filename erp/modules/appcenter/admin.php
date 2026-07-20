<?php
/**
 * App Center — অ্যাডমিন প্যানেল (অ্যাপ যোগ/এডিট/ডিলিট)
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requirePermission('appcenter.manage');

$pageTitle = 'App ম্যানেজ';
$db = Database::getInstance();

// টেবিল নিশ্চিত করা (frontend প্রথমে না খুললেও যেন কাজ করে)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    if ($action === 'save') {
        $data = [
            'name'         => trim(input('name')),
            'description'  => trim(input('description')),
            'category'     => trim(input('category')) ?: 'General',
            'icon'         => trim(input('icon')) ?: 'bi-app',
            'color'        => trim(input('color')) ?: 'blue',
            'network_path' => trim(input('network_path')),
            'silent_args'  => trim(input('silent_args')),
            'version'      => trim(input('version')),
            'is_active'    => (int) input('is_active', 1),
            'sort_order'   => (int) input('sort_order', 0),
        ];
        if ($data['name'] === '' || $data['network_path'] === '') {
            flash('error', 'নাম ও নেটওয়ার্ক পাথ দুটোই দরকার।');
        } else {
            $id = (int) input('id');
            if ($id) { $db->update('apps', $data, 'id = ?', [$id]); flash('success', 'অ্যাপ আপডেট হয়েছে।'); }
            else     { $db->insert('apps', $data);                  flash('success', 'নতুন অ্যাপ যোগ হয়েছে।'); }
        }
    }
    if ($action === 'delete') {
        $db->delete('apps', 'id = ?', [(int) input('id')]);
        flash('success', 'অ্যাপ ডিলিট হয়েছে।');
    }
    redirect('admin.php');
}

$apps = $db->fetchAll("SELECT * FROM apps ORDER BY sort_order, name");
$colors = ['blue','green','orange','purple','red','gray'];

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>App ম্যানেজ</h1><div class="sub">নেটওয়ার্ক শেয়ারের সফটওয়্যার এখানে যোগ করুন — মোট <?= count($apps) ?>টি</div></div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= url('modules/appcenter/index.php') ?>"><i class="bi bi-grid"></i> ফ্রন্টএন্ড দেখুন</a>
    <button class="btn btn-primary" onclick="openAppModal()"><i class="bi bi-plus-lg"></i> নতুন অ্যাপ</button>
  </div>
</div>

<div class="alert" style="background:rgba(59,130,246,.1);color:#2563EB;border:1px solid rgba(59,130,246,.25)">
  <i class="bi bi-info-circle"></i>
  <div>বেস শেয়ার: <code><?= e(NETWORK_SHARE_BASE) ?></code> — relative path দিলে এর সাথে জোড়া লাগবে।
  full path (<code>\\server\...</code>) দিলে সেটাই ব্যবহার হবে। <b>config.php</b>-এ বেস বদলাতে পারবেন।</div>
</div>

<div class="card-c tight">
  <table class="table-c">
    <thead><tr><th>অ্যাপ</th><th>ক্যাটাগরি</th><th>নেটওয়ার্ক পাথ</th><th>Silent Args</th><th>স্ট্যাটাস</th><th style="text-align:right">অ্যাকশন</th></tr></thead>
    <tbody>
      <?php foreach ($apps as $a): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <span class="app-icon <?= e($a['color']) ?>" style="width:38px;height:38px;font-size:18px;border-radius:10px"><i class="bi <?= e($a['icon']) ?>"></i></span>
              <div><div style="font-weight:600"><?= e($a['name']) ?></div>
              <?php if ($a['version']): ?><small style="color:var(--text-muted)">v<?= e($a['version']) ?></small><?php endif; ?></div>
            </div>
          </td>
          <td><span class="badge-c badge-gray"><?= e($a['category']) ?></span></td>
          <td style="color:var(--text-muted);max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><code><?= e($a['network_path']) ?></code></td>
          <td style="color:var(--text-muted)"><code><?= e($a['silent_args'] ?: '—') ?></code></td>
          <td><?= $a['is_active'] ? '<span class="badge-c badge-green">চালু</span>' : '<span class="badge-c badge-red">বন্ধ</span>' ?></td>
          <td style="text-align:right;white-space:nowrap">
            <button class="btn btn-warning btn-sm" onclick='editApp(<?= json_encode($a, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" style="display:inline" onsubmit="return confirm('এই অ্যাপ ডিলিট করবেন?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$apps): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:30px">এখনো কোনো অ্যাপ নেই।</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-c" id="appModal">
  <div class="modal-box" style="max-width:560px"><form method="post">
    <div class="card-head"><h3 id="appModalTitle">নতুন অ্যাপ</h3><button type="button" class="icon-btn" onclick="closeModal('appModal')"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="a_id">
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">অ্যাপের নাম *</label><input class="form-control" name="name" id="a_name" required placeholder="Google Chrome"></div>
        <div class="form-group"><label class="form-label">ভার্সন</label><input class="form-control" name="version" id="a_ver" placeholder="120.0"></div>
      </div>
      <div class="form-group"><label class="form-label">বিবরণ</label><input class="form-control" name="description" id="a_desc" placeholder="দ্রুত ও নিরাপদ ওয়েব ব্রাউজার"></div>
      <div class="form-group">
        <label class="form-label">নেটওয়ার্ক পাথ * <small style="color:var(--text-muted)">(ইনস্টলার ফাইল)</small></label>
        <input class="form-control" name="network_path" id="a_path" required placeholder="Chrome\ChromeSetup.exe  অথবা  \\SERVER\Software\Chrome\ChromeSetup.exe">
      </div>
      <div class="form-group">
        <label class="form-label">Silent Install আর্গুমেন্ট <small style="color:var(--text-muted)">(না দিলে সাধারণভাবে চলবে)</small></label>
        <input class="form-control" name="silent_args" id="a_args" placeholder="যেমন: /silent /install  অথবা  /qn">
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">ক্যাটাগরি</label><input class="form-control" name="category" id="a_cat" placeholder="Browser" value="General"></div>
        <div class="form-group"><label class="form-label">ক্রম (Sort)</label><input type="number" class="form-control" name="sort_order" id="a_sort" value="0"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">আইকন <small style="color:var(--text-muted)">(Bootstrap Icon)</small></label>
          <input class="form-control" name="icon" id="a_icon" value="bi-app" placeholder="bi-google"></div>
        <div class="form-group"><label class="form-label">রঙ</label>
          <select class="form-control" name="color" id="a_color">
            <?php foreach ($colors as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <div class="form-group"><label class="form-label">স্ট্যাটাস</label>
        <select class="form-control" name="is_active" id="a_active"><option value="1">চালু</option><option value="0">বন্ধ</option></select></div>
      <p style="font-size:12px;color:var(--text-muted);margin:0">
        💡 আইকনের নাম দেখুন: <b>icons.getbootstrap.com</b> (যেমন bi-google, bi-microsoft, bi-filetype-pdf)
      </p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('appModal')">বাতিল</button>
      <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> সংরক্ষণ</button>
    </div>
  </form></div>
</div>

<script>
function openAppModal(){
  document.getElementById('appModalTitle').textContent='নতুন অ্যাপ';
  ['a_id','a_name','a_ver','a_desc','a_path','a_args'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('a_cat').value='General'; document.getElementById('a_sort').value=0;
  document.getElementById('a_icon').value='bi-app'; document.getElementById('a_color').value='blue';
  document.getElementById('a_active').value='1';
  openModal('appModal');
}
function editApp(a){
  document.getElementById('appModalTitle').textContent='অ্যাপ এডিট';
  document.getElementById('a_id').value=a.id;
  document.getElementById('a_name').value=a.name;
  document.getElementById('a_ver').value=a.version||'';
  document.getElementById('a_desc').value=a.description||'';
  document.getElementById('a_path').value=a.network_path;
  document.getElementById('a_args').value=a.silent_args||'';
  document.getElementById('a_cat').value=a.category||'General';
  document.getElementById('a_sort').value=a.sort_order;
  document.getElementById('a_icon').value=a.icon;
  document.getElementById('a_color').value=a.color;
  document.getElementById('a_active').value=a.is_active;
  openModal('appModal');
}
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
