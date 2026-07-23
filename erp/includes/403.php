<?php $pageTitle = 'Access Denied'; require __DIR__ . '/header.php'; ?>
<div class="card-c" style="text-align:center;padding:60px 24px">
  <i class="bi bi-shield-lock" style="font-size:56px;color:var(--danger)"></i>
  <h1 style="margin:18px 0 8px">৪০৩ — অনুমতি নেই</h1>
  <p style="color:var(--text-muted)">দুঃখিত, এই পেজ দেখার অনুমতি আপনার নেই। প্রয়োজনে অ্যাডমিনের সাথে যোগাযোগ করুন।</p>
  <a class="btn btn-primary" style="margin-top:18px;width:auto" href="<?= url('index.php') ?>">
    <i class="bi bi-house"></i> ড্যাশবোর্ডে ফিরে যান
  </a>
</div>
<?php require __DIR__ . '/footer.php'; ?>
