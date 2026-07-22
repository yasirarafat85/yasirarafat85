<?php
/**
 * ===============================================================
 *  উদাহরণ MODULE: Inventory (ইনভেন্টরি)
 * ===============================================================
 *  এটি দেখায় কীভাবে একটা নতুন প্রজেক্ট (module) বানাতে হয় —
 *  bootstrap include করুন, permission চেক করুন, Database লেয়ার
 *  দিয়ে ডেটা নিন, তারপর header/footer দিয়ে UI দেখান।
 *
 *  নতুন module বানাতে এই ফোল্ডারটা কপি করে নাম বদলে নিলেই হবে।
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requirePermission('inventory.view');

$pageTitle = 'ইনভেন্টরি';
$db = Database::getInstance();

// --- module নিজের টেবিল তৈরি করে নেয় (প্রথমবার) ---
$db->query("CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(80) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    location VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    if ($action === 'save' && (Auth::can('inventory.create') || Auth::can('inventory.edit'))) {
        $data = [
            'name'       => trim(input('name')),
            'category'   => trim(input('category')),
            'quantity'   => (int) input('quantity', 0),
            'unit_price' => (float) input('unit_price', 0),
            'location'   => trim(input('location')),
        ];
        $id = (int) input('id');
        if ($id && Auth::can('inventory.edit'))   { $db->update('inventory_items', $data, 'id = ?', [$id]); flash('success', 'আইটেম আপডেট হয়েছে।'); }
        elseif (!$id && Auth::can('inventory.create')) { $db->insert('inventory_items', $data);              flash('success', 'নতুন আইটেম যোগ হয়েছে।'); }
    }
    if ($action === 'delete' && Auth::can('inventory.delete')) {
        $db->delete('inventory_items', 'id = ?', [(int) input('id')]);
        flash('success', 'আইটেম ডিলিট হয়েছে।');
    }
    redirect('modules/inventory/index.php');
}

$items      = $db->fetchAll("SELECT * FROM inventory_items ORDER BY id DESC");
$totalValue = (float) $db->scalar("SELECT COALESCE(SUM(quantity * unit_price),0) FROM inventory_items");
$lowStock   = (int) $db->scalar("SELECT COUNT(*) FROM inventory_items WHERE quantity <= 5");

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>ইনভেন্টরি</h1><div class="sub">সরঞ্জাম ও স্টক ব্যবস্থাপনা — উদাহরণ module</div></div>
  <?php if (Auth::can('inventory.create')): ?>
    <button class="btn btn-primary" onclick="openItemModal()"><i class="bi bi-plus-lg"></i> নতুন আইটেম</button>
  <?php endif; ?>
</div>

<div class="grid grid-4" style="margin-bottom:22px">
  <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-box-seam"></i></div>
    <div><div class="stat-value"><?= count($items) ?></div><div class="stat-label">মোট আইটেম</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
    <div><div class="stat-value" style="color:#00897B">৳<?= number_format($totalValue) ?></div><div class="stat-label">মোট মূল্য</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="bi bi-exclamation-triangle"></i></div>
    <div><div class="stat-value" style="color:#F57C00"><?= $lowStock ?></div><div class="stat-label">কম স্টক (≤৫)</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="bi bi-tags"></i></div>
    <div><div class="stat-value" style="color:#8E24AA"><?= (int)$db->scalar("SELECT COUNT(DISTINCT category) FROM inventory_items") ?></div><div class="stat-label">ক্যাটাগরি</div></div></div>
</div>

<div class="card-c tight">
  <table class="table-c">
    <thead><tr><th>নাম</th><th>ক্যাটাগরি</th><th>পরিমাণ</th><th>একক মূল্য</th><th>মোট</th><th>অবস্থান</th><th style="text-align:right">অ্যাকশন</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td style="font-weight:600"><?= e($it['name']) ?></td>
          <td><span class="badge-c badge-gray"><?= e($it['category'] ?: '—') ?></span></td>
          <td><?php if ($it['quantity'] <= 5): ?><span class="badge-c badge-red"><?= $it['quantity'] ?></span><?php else: ?><?= $it['quantity'] ?><?php endif; ?></td>
          <td>৳<?= number_format($it['unit_price'], 2) ?></td>
          <td style="font-weight:600">৳<?= number_format($it['quantity'] * $it['unit_price'], 2) ?></td>
          <td style="color:var(--text-muted)"><?= e($it['location'] ?: '—') ?></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if (Auth::can('inventory.edit')): ?>
              <button class="btn btn-warning btn-sm" onclick='editItem(<?= json_encode($it, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
            <?php endif; ?>
            <?php if (Auth::can('inventory.delete')): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('ডিলিট করবেন?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $it['id'] ?>">
                <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">এখনো কোনো আইটেম নেই। "নতুন আইটেম" দিয়ে শুরু করুন।</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-c" id="itemModal">
  <div class="modal-box"><form method="post">
    <div class="card-head"><h3 id="itemModalTitle">নতুন আইটেম</h3><button type="button" class="icon-btn" onclick="closeModal('itemModal')"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="i_id">
      <div class="form-group"><label class="form-label">নাম</label><input class="form-control" name="name" id="i_name" required></div>
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">ক্যাটাগরি</label><input class="form-control" name="category" id="i_cat" placeholder="যেমন: ল্যাপটপ"></div>
        <div class="form-group"><label class="form-label">অবস্থান</label><input class="form-control" name="location" id="i_loc" placeholder="যেমন: রুম ৩"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label class="form-label">পরিমাণ</label><input type="number" class="form-control" name="quantity" id="i_qty" value="0"></div>
        <div class="form-group"><label class="form-label">একক মূল্য (৳)</label><input type="number" step="0.01" class="form-control" name="unit_price" id="i_price" value="0"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('itemModal')">বাতিল</button>
      <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> সংরক্ষণ</button>
    </div>
  </form></div>
</div>

<script>
function openItemModal(){
  document.getElementById('itemModalTitle').textContent='নতুন আইটেম';
  ['i_id','i_name','i_cat','i_loc'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('i_qty').value=0; document.getElementById('i_price').value=0;
  openModal('itemModal');
}
function editItem(it){
  document.getElementById('itemModalTitle').textContent='আইটেম এডিট';
  document.getElementById('i_id').value=it.id;
  document.getElementById('i_name').value=it.name;
  document.getElementById('i_cat').value=it.category||'';
  document.getElementById('i_loc').value=it.location||'';
  document.getElementById('i_qty').value=it.quantity;
  document.getElementById('i_price').value=it.unit_price;
  openModal('itemModal');
}
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
