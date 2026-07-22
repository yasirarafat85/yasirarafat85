<?php
/**
 * App Center — অ্যাডমিন প্যানেল (অ্যাপ যোগ/এডিট/ডিলিট)
 * - network অ্যাপ: ইনস্টলার ফাইল আপলোড → শেয়ার পাথে জমা, path অটো
 * - winget অ্যাপ: তালিকা থেকে বেছে বা সার্ভারে সার্চ করে যোগ
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/_schema.php';
Auth::requirePermission('appcenter.manage');

$pageTitle = 'App ম্যানেজ';
$db = Database::getInstance();
appcenter_ensure_schema($db);
$sharePath = appcenter_share_path($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');

    if ($action === 'save') {
        $type = input('install_type') === 'winget' ? 'winget' : 'network';
        $name = trim(input('name'));
        $networkPath = trim(input('network_path'));
        $silentArgs  = trim(input('silent_args'));
        $uploadError = null;

        // ---- network: ইনস্টলার ফাইল আপলোড (থাকলে) ----
        if ($type === 'network' && !empty($_FILES['installer']['name'])) {
            $f = $_FILES['installer'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $uploadError = 'ফাইল আপলোড ব্যর্থ (কোড ' . $f['error'] . ')। বড় ফাইল হলে php.ini-তে upload_max_filesize/post_max_size বাড়ান।';
            } else {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $allowed = ['exe', 'msi', 'msix', 'appx', 'bat', 'cmd', 'zip'];
                if (!in_array($ext, $allowed, true)) {
                    $uploadError = 'শুধু ' . implode('/', $allowed) . ' ফাইল আপলোড করা যায়।';
                } elseif (!is_dir($sharePath) || !is_writable($sharePath)) {
                    $uploadError = 'শেয়ার পাথে (' . e($sharePath) . ') লেখা যাচ্ছে না। Settings-এ পাথ ও অ্যাক্সেস ঠিক করুন।';
                } else {
                    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($f['name']));
                    $dest = $sharePath . '\\' . $safe;
                    if (@move_uploaded_file($f['tmp_name'], $dest)) {
                        $networkPath = $dest;
                        if ($name === '')       $name = pathinfo($safe, PATHINFO_FILENAME);
                        if ($silentArgs === '' && $ext === 'msi') $silentArgs = '/qn /norestart';
                    } else {
                        $uploadError = 'ফাইল শেয়ারে কপি করা যায়নি: ' . e($dest);
                    }
                }
            }
        }

        if ($uploadError) {
            flash('error', $uploadError);
            redirect('modules/appcenter/admin.php');
        }

        $data = [
            'name'         => $name,
            'description'  => trim(input('description')),
            'category'     => trim(input('category')) ?: 'General',
            'icon'         => trim(input('icon')) ?: 'bi-app',
            'color'        => trim(input('color')) ?: 'blue',
            'install_type' => $type,
            'network_path' => $networkPath,
            'winget_id'    => trim(input('winget_id')),
            'silent_args'  => $silentArgs,
            'version'      => trim(input('version')),
            'is_active'    => (int) input('is_active', 1),
            'sort_order'   => (int) input('sort_order', 0),
        ];
        $missing = $type === 'winget' ? $data['winget_id'] === '' : $data['network_path'] === '';
        if ($data['name'] === '' || $missing) {
            flash('error', $type === 'winget' ? 'নাম ও winget ID দরকার।' : 'নাম দরকার, আর একটি ফাইল আপলোড করুন বা পাথ দিন।');
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
    redirect('modules/appcenter/admin.php');
}

$apps = $db->fetchAll("SELECT * FROM apps ORDER BY sort_order, name");
$colors = ['blue','green','orange','purple','red','gray'];

// জনপ্রিয় winget অ্যাপ (তালিকা থেকে বেছে নেওয়ার জন্য)
$commonWinget = [
    'Google Chrome'=>'Google.Chrome','Mozilla Firefox'=>'Mozilla.Firefox','Microsoft Edge'=>'Microsoft.Edge',
    '7-Zip'=>'7zip.7zip','WinRAR'=>'RARLab.WinRAR','VLC media player'=>'VideoLAN.VLC',
    'Adobe Acrobat Reader'=>'Adobe.Acrobat.Reader.64-bit','Foxit Reader'=>'Foxit.FoxitReader',
    'Notepad++'=>'Notepad++.Notepad++','LibreOffice'=>'TheDocumentFoundation.LibreOffice',
    'Zoom'=>'Zoom.Zoom','Microsoft Teams'=>'Microsoft.Teams','Skype'=>'Microsoft.Skype',
    'AnyDesk'=>'AnyDeskSoftwareGmbH.AnyDesk','TeamViewer'=>'TeamViewer.TeamViewer',
    'Google Drive'=>'Google.GoogleDrive','Dropbox'=>'Dropbox.Dropbox',
    'VS Code'=>'Microsoft.VisualStudioCode','Git'=>'Git.Git','Node.js'=>'OpenJS.NodeJS',
    'Python 3'=>'Python.Python.3.12','PuTTY'=>'PuTTY.PuTTY','FileZilla'=>'TimKosse.FileZilla.Client',
    'GIMP'=>'GIMP.GIMP','Audacity'=>'Audacity.Audacity','OBS Studio'=>'OBSProject.OBSStudio',
    'WhatsApp'=>'WhatsApp.WhatsApp','Slack'=>'SlackTechnologies.Slack','Spotify'=>'Spotify.Spotify',
    'Postman'=>'Postman.Postman','Notion'=>'Notion.Notion','Brave'=>'Brave.Brave',
];

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>App ম্যানেজ</h1><div class="sub">ফাইল আপলোড বা winget দিয়ে সফটওয়্যার যোগ করুন — মোট <?= count($apps) ?>টি</div></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-ghost" href="<?= url('modules/appcenter/settings.php') ?>"><i class="bi bi-gear"></i> Settings</a>
    <a class="btn btn-ghost" href="<?= url('modules/appcenter/logs.php') ?>"><i class="bi bi-clock-history"></i> Logs</a>
    <a class="btn btn-ghost" href="<?= url('modules/appcenter/index.php') ?>"><i class="bi bi-grid"></i> ফ্রন্টএন্ড</a>
    <button class="btn btn-primary" onclick="openAppModal()"><i class="bi bi-plus-lg"></i> নতুন অ্যাপ</button>
  </div>
</div>

<div class="alert" style="background:rgba(59,130,246,.1);color:#2563EB;border:1px solid rgba(59,130,246,.25)">
  <i class="bi bi-info-circle"></i>
  <div>আপলোড শেয়ার: <code><?= e($sharePath) ?></code>
    <?php if (!is_dir($sharePath) || !is_writable($sharePath)): ?>
      — <b style="color:#DC2626">লেখা যাচ্ছে না!</b> <a href="<?= url('modules/appcenter/settings.php') ?>" style="color:#2563EB;font-weight:600">Settings-এ ঠিক করুন →</a>
    <?php endif; ?>
  </div>
</div>

<div class="card-c tight">
  <table class="table-c">
    <thead><tr><th>অ্যাপ</th><th>ধরন</th><th>উৎস</th><th>Silent Args</th><th>স্ট্যাটাস</th><th style="text-align:right">অ্যাকশন</th></tr></thead>
    <tbody>
      <?php foreach ($apps as $a): $isWinget = ($a['install_type'] === 'winget'); ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <span class="app-icon <?= e($a['color']) ?>" style="width:38px;height:38px;font-size:18px;border-radius:10px"><i class="bi <?= e($a['icon']) ?>"></i></span>
              <div><div style="font-weight:600"><?= e($a['name']) ?></div>
              <small style="color:var(--text-muted)"><?= e($a['category']) ?><?= $a['version'] ? ' · v'.e($a['version']) : '' ?></small></div>
            </div>
          </td>
          <td><?= $isWinget ? '<span class="badge-c badge-blue"><i class="bi bi-box"></i> winget</span>' : '<span class="badge-c badge-gray"><i class="bi bi-hdd-network"></i> নেটওয়ার্ক</span>' ?></td>
          <td style="color:var(--text-muted);max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><code><?= e($isWinget ? $a['winget_id'] : basename(str_replace('\\','/',$a['network_path'] ?? ''))) ?></code></td>
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
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:30px">এখনো কোনো অ্যাপ নেই। "নতুন অ্যাপ" দিয়ে শুরু করুন।</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ================= Modal ================= -->
<div class="modal-c" id="appModal">
  <div class="modal-box" style="max-width:560px">
    <form method="post" enctype="multipart/form-data">
      <div class="card-head"><h3 id="appModalTitle">নতুন অ্যাপ</h3><button type="button" class="icon-btn" onclick="closeModal('appModal')"><i class="bi bi-x-lg"></i></button></div>
      <div class="modal-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="a_id">

        <!-- ধরন বেছে নিন -->
        <div class="form-group">
          <label class="form-label">কীভাবে যোগ করবেন?</label>
          <div style="display:flex;gap:8px">
            <label class="type-pick"><input type="radio" name="install_type" value="network" id="type_net" checked onchange="toggleType()"> <i class="bi bi-upload"></i> ফাইল আপলোড</label>
            <label class="type-pick"><input type="radio" name="install_type" value="winget" id="type_wg" onchange="toggleType()"> <i class="bi bi-box"></i> Winget</label>
          </div>
        </div>

        <!-- ===== UPLOAD (network) ===== -->
        <div id="grp_net">
          <div class="form-group" id="grp_file">
            <label class="form-label">ইনস্টলার ফাইল <small style="color:var(--text-muted)">(.exe / .msi)</small></label>
            <input type="file" class="form-control" name="installer" id="a_file" accept=".exe,.msi,.msix,.appx,.zip,.bat,.cmd" onchange="onFilePick(this)">
            <p id="uploadNote" style="font-size:12px;color:var(--text-muted);margin:6px 0 0">ফাইল দিলে path নিজে থেকে <code><?= e($sharePath) ?></code>-এ যাবে।</p>
          </div>
          <div class="form-group" id="grp_curpath" style="display:none">
            <label class="form-label">বর্তমান ফাইল</label>
            <input class="form-control" name="network_path" id="a_path" readonly style="background:var(--bg-secondary);font-family:monospace;font-size:12px">
            <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0">নতুন ফাইল আপলোড করলে এটি বদলে যাবে।</p>
          </div>
        </div>

        <!-- ===== WINGET ===== -->
        <div id="grp_winget" style="display:none">
          <div class="form-group">
            <label class="form-label">অ্যাপ বেছে নিন <small style="color:var(--text-muted)">(টাইপ করে খুঁজুন)</small></label>
            <input class="form-control" list="wgList" id="a_wgpick" placeholder="যেমন: 7-Zip" oninput="onWgPick()">
            <datalist id="wgList">
              <?php foreach ($commonWinget as $n => $wid): ?><option data-id="<?= e($wid) ?>" value="<?= e($n) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Winget ID *</label>
            <div style="display:flex;gap:8px">
              <input class="form-control" name="winget_id" id="a_winget" placeholder="Google.Chrome">
              <button type="button" class="btn btn-ghost" style="white-space:nowrap" onclick="wingetSearch()"><i class="bi bi-search"></i> সার্ভারে খুঁজুন</button>
            </div>
            <div id="wgResults" style="margin-top:8px"></div>
          </div>
        </div>

        <!-- নাম (দুই ধরনেই) -->
        <div class="grid grid-2">
          <div class="form-group"><label class="form-label">অ্যাপের নাম *</label><input class="form-control" name="name" id="a_name" required placeholder="ফাইল দিলে অটো"></div>
          <div class="form-group"><label class="form-label">ক্যাটাগরি</label><input class="form-control" name="category" id="a_cat" value="General"></div>
        </div>

        <!-- Advanced (গোটানো) -->
        <details style="margin-top:4px">
          <summary style="cursor:pointer;color:var(--primary);font-weight:600;font-size:14px">আরও অপশন (ঐচ্ছিক)</summary>
          <div style="padding-top:14px">
            <div class="form-group"><label class="form-label">বিবরণ</label><input class="form-control" name="description" id="a_desc"></div>
            <div class="form-group"><label class="form-label">Silent Install আর্গুমেন্ট <small style="color:var(--text-muted)">(.msi সাধারণত /qn)</small></label>
              <input class="form-control" name="silent_args" id="a_args" placeholder="/silent /install"></div>
            <div class="grid grid-2">
              <div class="form-group"><label class="form-label">ভার্সন</label><input class="form-control" name="version" id="a_ver"></div>
              <div class="form-group"><label class="form-label">ক্রম (Sort)</label><input type="number" class="form-control" name="sort_order" id="a_sort" value="0"></div>
            </div>
            <div class="grid grid-2">
              <div class="form-group"><label class="form-label">আইকন</label><input class="form-control" name="icon" id="a_icon" value="bi-app"></div>
              <div class="form-group"><label class="form-label">রঙ</label>
                <select class="form-control" name="color" id="a_color"><?php foreach ($colors as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label class="form-label">স্ট্যাটাস</label>
              <select class="form-control" name="is_active" id="a_active"><option value="1">চালু</option><option value="0">বন্ধ</option></select></div>
          </div>
        </details>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('appModal')">বাতিল</button>
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> সংরক্ষণ</button>
      </div>
    </form>
  </div>
</div>

<style>
.type-pick{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border:1.5px solid var(--border);
  border-radius:10px;cursor:pointer;font-weight:600;font-size:14px}
.type-pick:has(input:checked){border-color:var(--primary);background:rgba(240,83,64,.08);color:var(--primary)}
.type-pick input{display:none}
.wg-res{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 11px;border:1px solid var(--border);
  border-radius:8px;margin-bottom:6px;font-size:13px}
.wg-res code{color:var(--text-muted)}
</style>

<script>
const WG = <?= json_encode($commonWinget, JSON_UNESCAPED_UNICODE) ?>;

function toggleType(){
  const wg = document.getElementById('type_wg').checked;
  document.getElementById('grp_net').style.display    = wg ? 'none' : '';
  document.getElementById('grp_winget').style.display = wg ? '' : 'none';
  document.getElementById('a_winget').required = wg;
}
// ফাইল বেছে নিলে নাম অটো
function onFilePick(inp){
  if (inp.files && inp.files[0] && !document.getElementById('a_name').value) {
    let n = inp.files[0].name.replace(/\.[^.]+$/,'').replace(/[._-]+/g,' ').trim();
    document.getElementById('a_name').value = n;
  }
}
// datalist থেকে winget অ্যাপ বাছলে id + নাম বসাও
function onWgPick(){
  const v = document.getElementById('a_wgpick').value;
  if (WG[v]) {
    document.getElementById('a_winget').value = WG[v];
    if (!document.getElementById('a_name').value) document.getElementById('a_name').value = v;
  }
}
// সার্ভারে winget search
function wingetSearch(){
  const q = document.getElementById('a_wgpick').value || document.getElementById('a_name').value;
  const box = document.getElementById('wgResults');
  if (!q) { box.innerHTML = '<span style="color:var(--text-muted);font-size:13px">আগে কিছু টাইপ করুন।</span>'; return; }
  box.innerHTML = '<span style="color:var(--text-muted);font-size:13px">খুঁজছি…</span>';
  fetch('<?= url('modules/appcenter/winget-search.php') ?>?q=' + encodeURIComponent(q))
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { box.innerHTML = '<span style="color:#DC2626;font-size:13px">'+d.error+'</span>'; return; }
      if (!d.results.length) { box.innerHTML = '<span style="color:var(--text-muted);font-size:13px">কিছু পাওয়া যায়নি।</span>'; return; }
      box.innerHTML = d.results.slice(0,8).map(r =>
        '<div class="wg-res"><span><b>'+r.name+'</b> <code>'+r.id+'</code> '+(r.version||'')+'</span>'+
        '<button type="button" class="btn btn-ghost btn-sm" onclick="pickWg(\''+r.id.replace(/'/g,"")+'\',\''+r.name.replace(/'/g,"")+'\')">বাছুন</button></div>'
      ).join('');
    })
    .catch(() => box.innerHTML = '<span style="color:#DC2626;font-size:13px">সার্চ ব্যর্থ।</span>');
}
function pickWg(id, name){
  document.getElementById('a_winget').value = id;
  if (!document.getElementById('a_name').value) document.getElementById('a_name').value = name;
  document.getElementById('wgResults').innerHTML = '<span style="color:#059669;font-size:13px">বাছাই: '+id+'</span>';
}

function openAppModal(){
  document.getElementById('appModalTitle').textContent='নতুন অ্যাপ';
  document.getElementById('a_id').value='';
  ['a_name','a_desc','a_ver','a_args','a_winget','a_wgpick','a_path'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('a_file').value='';
  document.getElementById('a_cat').value='General'; document.getElementById('a_sort').value=0;
  document.getElementById('a_icon').value='bi-app'; document.getElementById('a_color').value='blue';
  document.getElementById('a_active').value='1'; document.getElementById('wgResults').innerHTML='';
  document.getElementById('type_net').checked=true;
  document.getElementById('grp_file').style.display=''; document.getElementById('grp_curpath').style.display='none';
  toggleType(); openModal('appModal');
}
function editApp(a){
  openAppModal();
  document.getElementById('appModalTitle').textContent='অ্যাপ এডিট';
  document.getElementById('a_id').value=a.id;
  document.getElementById('a_name').value=a.name;
  document.getElementById('a_desc').value=a.description||'';
  document.getElementById('a_ver').value=a.version||'';
  document.getElementById('a_args').value=a.silent_args||'';
  document.getElementById('a_cat').value=a.category||'General';
  document.getElementById('a_sort').value=a.sort_order;
  document.getElementById('a_icon').value=a.icon;
  document.getElementById('a_color').value=a.color;
  document.getElementById('a_active').value=a.is_active;
  if (a.install_type === 'winget') {
    document.getElementById('type_wg').checked=true;
    document.getElementById('a_winget').value=a.winget_id||'';
  } else {
    document.getElementById('type_net').checked=true;
    document.getElementById('a_path').value=a.network_path||'';
    document.getElementById('grp_curpath').style.display='';  // এডিটে বর্তমান ফাইল দেখাই
  }
  toggleType();
}
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
