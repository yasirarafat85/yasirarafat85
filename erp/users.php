<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requirePermission('users.view');

$pageTitle = 'ইউজার ব্যবস্থাপনা';
$db = Database::getInstance();

// ---------- Actions (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');

    if ($action === 'create' && Auth::can('users.create')) {
        $email = trim(input('email'));
        if ($db->fetch("SELECT id FROM users WHERE email = ?", [$email])) {
            flash('error', 'এই ইমেইল আগে থেকেই আছে।');
        } else {
            $db->insert('users', [
                'name'     => trim(input('name')),
                'email'    => $email,
                'password' => password_hash(input('password') ?: 'password', PASSWORD_DEFAULT),
                'role_id'  => input('role_id') ?: null,
                'status'   => (int) input('status', 1),
            ]);
            flash('success', 'নতুন ইউজার যোগ হয়েছে।');
        }
    }

    if ($action === 'update' && Auth::can('users.edit')) {
        $id   = (int) input('id');
        $data = [
            'name'    => trim(input('name')),
            'email'   => trim(input('email')),
            'role_id' => input('role_id') ?: null,
            'status'  => (int) input('status', 1),
        ];
        if (input('password')) {
            $data['password'] = password_hash(input('password'), PASSWORD_DEFAULT);
        }
        $db->update('users', $data, 'id = ?', [$id]);
        flash('success', 'ইউজার আপডেট হয়েছে।');
    }

    if ($action === 'delete' && Auth::can('users.delete')) {
        $id = (int) input('id');
        if ($id === (int) $_SESSION['user_id']) {
            flash('error', 'নিজের অ্যাকাউন্ট ডিলিট করা যাবে না।');
        } else {
            $db->delete('users', 'id = ?', [$id]);
            flash('success', 'ইউজার ডিলিট হয়েছে।');
        }
    }
    redirect('users.php');
}

$users = $db->fetchAll(
    "SELECT u.*, r.name AS role_name FROM users u
     LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.id DESC"
);
$roles = $db->fetchAll("SELECT id, name FROM roles ORDER BY name");

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>ইউজার ব্যবস্থাপনা</h1><div class="sub">মোট <?= count($users) ?> জন ইউজার</div></div>
  <?php if (Auth::can('users.create')): ?>
    <button class="btn btn-primary" onclick="openUserModal()"><i class="bi bi-plus-lg"></i> নতুন ইউজার</button>
  <?php endif; ?>
</div>

<div class="card-c tight">
  <table class="table-c">
    <thead><tr><th>নাম</th><th>ইমেইল</th><th>Role</th><th>স্ট্যাটাস</th><th>শেষ লগইন</th><th style="text-align:right">অ্যাকশন</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td style="font-weight:600">
            <div style="display:flex;align-items:center;gap:10px">
              <span class="avatar" style="width:32px;height:32px;font-size:13px"><?= strtoupper(substr($u['name'],0,1)) ?></span>
              <?= e($u['name']) ?>
            </div>
          </td>
          <td style="color:var(--text-muted)"><?= e($u['email']) ?></td>
          <td><span class="badge-c badge-blue"><?= e($u['role_name'] ?? '—') ?></span></td>
          <td>
            <?php if ($u['status']): ?><span class="badge-c badge-green"><i class="bi bi-check-circle"></i> সক্রিয়</span>
            <?php else: ?><span class="badge-c badge-red"><i class="bi bi-x-circle"></i> নিষ্ক্রিয়</span><?php endif; ?>
          </td>
          <td style="color:var(--text-muted)"><?= $u['last_login'] ? e(date('d M, H:i', strtotime($u['last_login']))) : '—' ?></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if (Auth::can('users.edit')): ?>
              <button class="btn btn-warning btn-sm" onclick='editUser(<?= json_encode($u, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
            <?php endif; ?>
            <?php if (Auth::can('users.delete') && $u['id'] != $_SESSION['user_id']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('এই ইউজার ডিলিট করবেন?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ---------- Modal ---------- -->
<div class="modal-c" id="userModal">
  <div class="modal-box">
    <form method="post">
      <div class="card-head"><h3 id="userModalTitle">নতুন ইউজার</h3>
        <button type="button" class="icon-btn" onclick="closeModal('userModal')"><i class="bi bi-x-lg"></i></button></div>
      <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="f_action" value="create">
        <input type="hidden" name="id" id="f_id">
        <div class="form-group"><label class="form-label">নাম</label><input class="form-control" name="name" id="f_name" required></div>
        <div class="form-group"><label class="form-label">ইমেইল</label><input type="email" class="form-control" name="email" id="f_email" required></div>
        <div class="form-group"><label class="form-label">পাসওয়ার্ড <small id="pwHint" style="color:var(--text-muted)"></small></label>
          <input type="password" class="form-control" name="password" id="f_password" placeholder="••••••••"></div>
        <div class="grid grid-2">
          <div class="form-group"><label class="form-label">Role</label>
            <select class="form-control" name="role_id" id="f_role">
              <option value="">— নির্বাচন করুন —</option>
              <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="form-group"><label class="form-label">স্ট্যাটাস</label>
            <select class="form-control" name="status" id="f_status"><option value="1">সক্রিয়</option><option value="0">নিষ্ক্রিয়</option></select></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('userModal')">বাতিল</button>
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> সংরক্ষণ</button>
      </div>
    </form>
  </div>
</div>

<script>
function openUserModal(){
  document.getElementById('userModalTitle').textContent = 'নতুন ইউজার';
  document.getElementById('f_action').value = 'create';
  document.getElementById('f_id').value = '';
  document.getElementById('f_name').value = '';
  document.getElementById('f_email').value = '';
  document.getElementById('f_password').value = '';
  document.getElementById('f_password').required = true;
  document.getElementById('f_role').value = '';
  document.getElementById('f_status').value = '1';
  document.getElementById('pwHint').textContent = '';
  openModal('userModal');
}
function editUser(u){
  document.getElementById('userModalTitle').textContent = 'ইউজার এডিট';
  document.getElementById('f_action').value = 'update';
  document.getElementById('f_id').value = u.id;
  document.getElementById('f_name').value = u.name;
  document.getElementById('f_email').value = u.email;
  document.getElementById('f_password').value = '';
  document.getElementById('f_password').required = false;
  document.getElementById('pwHint').textContent = '(খালি রাখলে অপরিবর্তিত থাকবে)';
  document.getElementById('f_role').value = u.role_id || '';
  document.getElementById('f_status').value = u.status;
  openModal('userModal');
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
