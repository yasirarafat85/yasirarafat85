<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requirePermission('roles.manage');

$pageTitle = 'Role ও Permission';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');

    if ($action === 'create') {
        $name = trim(input('name'));
        if ($name && !$db->fetch("SELECT id FROM roles WHERE name = ?", [$name])) {
            $db->insert('roles', ['name' => $name, 'description' => trim(input('description'))]);
            flash('success', 'নতুন Role তৈরি হয়েছে।');
        } else {
            flash('error', 'নাম খালি অথবা আগে থেকেই আছে।');
        }
    }

    if ($action === 'update_permissions') {
        $roleId = (int) input('role_id');
        $perms  = $_POST['permissions'] ?? [];
        $db->delete('role_permissions', 'role_id = ?', [$roleId]);
        foreach ($perms as $pid) {
            $db->insert('role_permissions', ['role_id' => $roleId, 'permission_id' => (int) $pid]);
        }
        flash('success', 'Permission আপডেট হয়েছে।');
    }

    if ($action === 'delete') {
        $id = (int) input('id');
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if ($role && !$role['is_locked']) {
            $db->delete('roles', 'id = ?', [$id]);
            flash('success', 'Role ডিলিট হয়েছে।');
        } else {
            flash('error', 'এই Role ডিলিট করা যাবে না (locked)।');
        }
    }
    redirect('roles.php');
}

$roles = $db->fetchAll("SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count FROM roles r ORDER BY r.id");
$allPermissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module, id");

// module অনুযায়ী গ্রুপ
$permsByModule = [];
foreach ($allPermissions as $p) $permsByModule[$p['module']][] = $p;

// প্রতিটি role এর permission ids
$rolePerms = [];
foreach ($db->fetchAll("SELECT role_id, permission_id FROM role_permissions") as $rp) {
    $rolePerms[$rp['role_id']][] = (int) $rp['permission_id'];
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Role ও Permission</h1><div class="sub">কে কী করতে পারবে তা নিয়ন্ত্রণ করুন</div></div>
  <button class="btn btn-primary" onclick="openModal('roleModal')"><i class="bi bi-plus-lg"></i> নতুন Role</button>
</div>

<div class="grid grid-2" style="align-items:start">
  <!-- Roles list -->
  <div class="card-c tight">
    <div class="card-head"><h3>Role তালিকা</h3></div>
    <table class="table-c">
      <thead><tr><th>Role</th><th>ইউজার</th><th style="text-align:right">অ্যাকশন</th></tr></thead>
      <tbody>
        <?php foreach ($roles as $r): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= e($r['name']) ?>
                <?php if ($r['is_locked']): ?><i class="bi bi-lock-fill" style="font-size:11px;color:var(--text-muted)" title="Locked"></i><?php endif; ?>
              </div>
              <small style="color:var(--text-muted)"><?= e($r['description']) ?></small>
            </td>
            <td><span class="badge-c badge-gray"><?= $r['user_count'] ?> জন</span></td>
            <td style="text-align:right;white-space:nowrap">
              <button class="btn btn-warning btn-sm" onclick='editPerms(<?= (int)$r['id'] ?>, <?= json_encode($r['name'], JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($rolePerms[$r['id']] ?? []) ?>)'><i class="bi bi-sliders"></i> Permission</button>
              <?php if (!$r['is_locked']): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('এই Role ডিলিট করবেন?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Permission editor -->
  <div class="card-c" id="permPanel">
    <div id="permEmpty" style="text-align:center;padding:40px 0;color:var(--text-muted)">
      <i class="bi bi-shield-lock" style="font-size:40px"></i>
      <p>বামপাশ থেকে একটি Role বেছে<br>"Permission" বাটনে ক্লিক করুন।</p>
    </div>
    <form method="post" id="permForm" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_permissions">
      <input type="hidden" name="role_id" id="perm_role_id">
      <h3 style="margin-bottom:4px">Permission: <span id="perm_role_name" style="color:var(--primary)"></span></h3>
      <p class="sub" style="margin-bottom:16px">যেসব কাজের অনুমতি দিতে চান টিক দিন।</p>
      <?php foreach ($permsByModule as $module => $perms): ?>
        <div class="perm-block">
          <h4><i class="bi bi-folder"></i> <?= e(ucfirst($module)) ?></h4>
          <div class="checkbox-grid">
            <?php foreach ($perms as $p): ?>
              <label class="check">
                <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" data-perm="<?= $p['id'] ?>">
                <?= e($p['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-success" style="margin-top:6px"><i class="bi bi-check-lg"></i> Permission সংরক্ষণ</button>
    </form>
  </div>
</div>

<!-- Role modal -->
<div class="modal-c" id="roleModal">
  <div class="modal-box"><form method="post">
    <div class="card-head"><h3>নতুন Role</h3><button type="button" class="icon-btn" onclick="closeModal('roleModal')"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="form-group"><label class="form-label">Role নাম</label><input class="form-control" name="name" required placeholder="যেমন: Accountant"></div>
      <div class="form-group"><label class="form-label">বিবরণ</label><input class="form-control" name="description" placeholder="এই role কী করবে"></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')">বাতিল</button>
      <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> তৈরি করুন</button>
    </div>
  </form></div>
</div>

<script>
function editPerms(id, name, granted){
  document.getElementById('permEmpty').style.display = 'none';
  document.getElementById('permForm').style.display = 'block';
  document.getElementById('perm_role_id').value = id;
  document.getElementById('perm_role_name').textContent = name;
  document.querySelectorAll('#permForm input[type=checkbox]').forEach(cb => {
    cb.checked = granted.includes(parseInt(cb.dataset.perm));
  });
  document.getElementById('permPanel').scrollIntoView({behavior:'smooth', block:'nearest'});
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
