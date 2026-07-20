<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requirePermission('menus.manage');

$pageTitle = 'মেনু ব্যবস্থাপনা';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');

    if ($action === 'save') {
        $data = [
            'parent_id'  => input('parent_id') ?: null,
            'title'      => trim(input('title')),
            'url'        => trim(input('url')) ?: '#',
            'icon'       => trim(input('icon')) ?: 'bi-dot',
            'permission' => trim(input('permission')) ?: null,
            'sort_order' => (int) input('sort_order', 0),
            'is_active'  => (int) input('is_active', 1),
        ];
        $id = (int) input('id');
        if ($id) { $db->update('menus', $data, 'id = ?', [$id]); flash('success', 'মেনু আপডেট হয়েছে।'); }
        else     { $db->insert('menus', $data);                  flash('success', 'নতুন মেনু যোগ হয়েছে।'); }
    }

    if ($action === 'delete') {
        $db->delete('menus', 'id = ?', [(int) input('id')]);
        flash('success', 'মেনু ডিলিট হয়েছে।');
    }
    redirect('menus.php');
}

$menus       = $db->fetchAll("SELECT m.*, p.title AS parent_title FROM menus m LEFT JOIN menus p ON p.id = m.parent_id ORDER BY m.sort_order, m.id");
$parents     = $db->fetchAll("SELECT id, title FROM menus WHERE parent_id IS NULL ORDER BY sort_order");
$permissions = $db->fetchAll("SELECT slug, name FROM permissions ORDER BY module");

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>মেনু ব্যবস্থাপনা</h1><div class="sub">সাইডবারের মেনু এখান থেকে যোগ/সম্পাদনা করুন — নতুন module যোগ করা সহজ</div></div>
  <button class="btn btn-primary" onclick="openMenuModal()"><i class="bi bi-plus-lg"></i> নতুন মেনু</button>
</div>

<div class="card-c tight">
  <table class="table-c">
    <thead><tr><th>#</th><th>মেনু</th><th>লিংক</th><th>Permission</th><th>ধরন</th><th>স্ট্যাটাস</th><th style="text-align:right">অ্যাকশন</th></tr></thead>
    <tbody>
      <?php foreach ($menus as $m): ?>
        <tr>
          <td style="color:var(--text-muted)"><?= $m['sort_order'] ?></td>
          <td style="font-weight:600">
            <?php if ($m['parent_id']): ?><span style="color:var(--text-muted)">↳ </span><?php endif; ?>
            <i class="bi <?= e($m['icon']) ?>"></i> <?= e($m['title']) ?>
          </td>
          <td style="color:var(--text-muted)"><code><?= e($m['url']) ?></code></td>
          <td><?= $m['permission'] ? '<span class="badge-c badge-blue">'.e($m['permission']).'</span>' : '<span class="badge-c badge-gray">সবার জন্য</span>' ?></td>
          <td><?= $m['parent_id'] ? '<span class="badge-c badge-gray">সাব</span>' : '<span class="badge-c badge-blue">প্যারেন্ট</span>' ?></td>
          <td><?= $m['is_active'] ? '<span class="badge-c badge-green">চালু</span>' : '<span class="badge-c badge-red">বন্ধ</span>' ?></td>
          <td style="text-align:right;white-space:nowrap">
            <button class="btn btn-warning btn-sm" onclick='editMenu(<?= json_encode($m, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" style="display:inline" onsubmit="return confirm('এই মেনু ডিলিট করবেন?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-c" id="menuModal">
  <div class="modal-box"><form method="post">
    <div class="card-head"><h3 id="menuModalTitle">নতুন মেনু</h3><button type="button" class="icon-btn" onclick="closeModal('menuModal')"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="m_id">
      <div class="form-group"><label class="form-label">টাইটেল</label><input class="form-control" name="title" id="m_title" required></div>
      <div class="form-group"><label class="form-label">লিংক (URL)</label><input class="form-control" name="url" id="m_url" placeholder="modules/inventory/index.php"></div>
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">আইকন <small style="color:var(--text-muted)">(Bootstrap Icon)</small></label>
          <input class="form-control" name="icon" id="m_icon" placeholder="bi-box-seam"></div>
        <div class="form-group"><label class="form-label">ক্রম (Sort)</label><input type="number" class="form-control" name="sort_order" id="m_sort" value="0"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">প্যারেন্ট মেনু</label>
          <select class="form-control" name="parent_id" id="m_parent"><option value="">— শীর্ষ পর্যায় —</option>
            <?php foreach ($parents as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group"><label class="form-label">স্ট্যাটাস</label>
          <select class="form-control" name="is_active" id="m_active"><option value="1">চালু</option><option value="0">বন্ধ</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">প্রয়োজনীয় Permission</label>
        <select class="form-control" name="permission" id="m_perm"><option value="">— সবার জন্য দৃশ্যমান —</option>
          <?php foreach ($permissions as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('menuModal')">বাতিল</button>
      <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> সংরক্ষণ</button>
    </div>
  </form></div>
</div>

<script>
function openMenuModal(){
  document.getElementById('menuModalTitle').textContent='নতুন মেনু';
  ['m_id','m_title','m_url','m_icon'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('m_sort').value=0; document.getElementById('m_parent').value='';
  document.getElementById('m_active').value='1'; document.getElementById('m_perm').value='';
  openModal('menuModal');
}
function editMenu(m){
  document.getElementById('menuModalTitle').textContent='মেনু এডিট';
  document.getElementById('m_id').value=m.id;
  document.getElementById('m_title').value=m.title;
  document.getElementById('m_url').value=m.url;
  document.getElementById('m_icon').value=m.icon;
  document.getElementById('m_sort').value=m.sort_order;
  document.getElementById('m_parent').value=m.parent_id||'';
  document.getElementById('m_active').value=m.is_active;
  document.getElementById('m_perm').value=m.permission||'';
  openModal('menuModal');
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
