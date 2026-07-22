<?php
// learn.php — একটা ক্যাটাগরির শেখার নোট, উদাহরণ ও র‍্যান্ডম চ্যালেঞ্জ (লেভেল ১)
require_once __DIR__ . '/includes/auth.php';
require_login();

$slug = clean($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
$stmt->execute([$slug]);
$cat = $stmt->fetch();

if (!$cat) {
    set_flash('danger', 'ক্যাটাগরি পাওয়া যায়নি।');
    redirect('categories.php');
}

// র‍্যান্ডম চ্যালেঞ্জ (প্রতি ক্যাটাগরির জন্য কয়েকটা)
$challenges = [
    'facebook' => [
        'তোমার প্রিয় এক কাপ চা নিয়ে ৩ লাইনের একটা হুক লেখো।',
        'একটা নতুন দক্ষতা শেখার অভিজ্ঞতা নিয়ে স্ক্রল-থামানো পোস্ট লেখো।',
        'পাঠককে কমেন্ট করতে উৎসাহ দেয় এমন একটা প্রশ্ন দিয়ে পোস্ট শুরু করো।',
    ],
    'sales' => [
        'একটা হাতে বানানো মোমবাতি বিক্রির জন্য PAS ফর্মুলায় ৪ লাইন লেখো।',
        'একটা অনলাইন কোর্সের জন্য সেলস কপি লেখো — সমস্যা দিয়ে শুরু করো।',
        'একটা হারবাল তেলের জন্য জরুরি ভাব (urgency) সহ কপি লেখো।',
    ],
    'blog' => [
        '"সকালে ঘুম থেকে ওঠার উপকারিতা" নিয়ে একটা আকর্ষণীয় শিরোনাম ও ভূমিকা লেখো।',
        'নতুনদের জন্য "৫টি সহজ রান্নার টিপস" — তালিকা আর্টিকেলের শুরু লেখো।',
        'একটা সাধারণ সমস্যার সমাধান নিয়ে ব্লগের ভূমিকা লেখো।',
    ],
    'story' => [
        'তোমার জীবনের এমন একটা মুহূর্ত যা তোমাকে বদলে দিয়েছিল — ৪ লাইনে লেখো।',
        '"Show, don\'t tell" ব্যবহার করে একটা হতাশার মুহূর্ত বর্ণনা করো।',
        'ছোটবেলার একটা শিক্ষণীয় স্মৃতি নিয়ে ছোট গল্প লেখো।',
    ],
];
$list = $challenges[$slug] ?? ['এই বিষয়ে একটা ছোট লেখা অনুশীলন করো।'];
$challenge = $list[array_rand($list)];

$pageTitle = $cat['name'] . ' শেখা';
require __DIR__ . '/partials/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="categories.php">শেখা</a></li>
    <li class="breadcrumb-item active"><?= e($cat['name']) ?></li>
</ol></nav>

<h3 class="mb-1"><?= e($cat['name']) ?></h3>
<p class="text-muted"><?= e($cat['description']) ?></p>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card p-4 h-100">
            <h5 class="card-title">📌 টিপস ও নিয়ম</h5>
            <div class="tips-list"><?= e($cat['tips']) ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-4 h-100">
            <h5 class="card-title">✨ উদাহরণ</h5>
            <div class="example-box mb-3"><?= e($cat['example_1']) ?></div>
            <div class="example-box"><?= e($cat['example_2']) ?></div>
        </div>
    </div>
</div>

<div class="card p-4 mt-3">
    <h5 class="card-title">🎯 আজকের চ্যালেঞ্জ</h5>
    <div class="challenge-box mb-3"><?= e($challenge) ?></div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="learn.php?slug=<?= e($slug) ?>" class="btn btn-outline-secondary btn-sm">🔄 নতুন চ্যালেঞ্জ</a>
        <a href="writing_edit.php?category_id=<?= (int)$cat['id'] ?>" class="btn btn-brand btn-sm">✍️ এখনই লিখি</a>
        <a href="generate.php?category_id=<?= (int)$cat['id'] ?>" class="btn btn-accent btn-sm">⚡ টেমপ্লেট দিয়ে খসড়া</a>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
