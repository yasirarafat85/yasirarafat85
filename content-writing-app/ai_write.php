<?php
// ai_write.php — লেভেল ৩: ইউজারের নির্দেশ থেকে AI নতুন কনটেন্ট লেখে
// উন্নত: কন্ট্রোল ইনপুট + রিচ প্লেবুক + few-shot + chain-of-thought + দৈর্ঘ্য
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai_client.php';
$user = require_login();
$uid = $user['id'];
global $AI_PROVIDERS;

$cats = db()->query('SELECT id, name, slug FROM categories ORDER BY id')->fetchAll();
$settings = get_ai_settings($uid);
$providerModels = $AI_PROVIDERS[$settings['provider']]['models'] ?? [];

// ইউজার-নির্বাচনযোগ্য টোন ও দৈর্ঘ্যের অপশন
$toneOptions = [
    'auto'        => 'স্বয়ংক্রিয় (প্রতিবার আলাদা)',
    'friendly'    => 'আন্তরিক ও বন্ধুসুলভ',
    'inspiring'   => 'উদ্দীপক ও অনুপ্রেরণামূলক',
    'educational' => 'সহজ ও শিক্ষামূলক',
    'funny'       => 'হালকা ও মজার',
    'bold'        => 'আত্মবিশ্বাসী ও জোরালো',
];
$lengthOptions = [
    'short'  => 'ছোট (৪–৬ লাইন)',
    'medium' => 'মাঝারি (৮–১২ লাইন)',
    'long'   => 'বিস্তারিত (৩৫০–৫০০ শব্দ)',
];
$goalOptions = [
    'reach'      => 'সর্বোচ্চ রিচ (শেয়ার/সেভযোগ্য)',
    'engagement' => 'বেশি কমেন্ট/এনগেজমেন্ট',
    'sales'      => 'বিক্রি বাড়ানো',
    'inform'     => 'তথ্য/শিক্ষা দেওয়া',
];

$generated = '';
$error = '';
// ফর্মের মান ধরে রাখা
$brief = ''; $categoryId = (int)($_GET['category_id'] ?? 0);
$tone = 'auto'; $length = 'medium'; $goal = 'reach'; $audience = ''; $notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $brief      = clean($_POST['brief'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $model      = clean($_POST['model'] ?? '') ?: null;
    $tone       = clean($_POST['tone'] ?? 'auto');
    $length     = clean($_POST['length'] ?? 'medium');
    $goal       = clean($_POST['goal'] ?? 'reach');
    $audience   = clean($_POST['audience'] ?? '');
    $notes      = clean($_POST['notes'] ?? '');

    if ($brief === '') {
        $error = 'কী লিখতে হবে সেটা লিখুন।';
    } else {
        // ক্যাটাগরির slug ও উদাহরণ (few-shot) বের করা
        $catSlug = '';
        foreach ($cats as $c) if ((int)$c['id'] === $categoryId) $catSlug = $c['slug'];
        $ex = ['example_1' => '', 'example_2' => ''];
        if ($categoryId) {
            $exs = db()->prepare('SELECT example_1, example_2 FROM categories WHERE id = ?');
            $exs->execute([$categoryId]);
            $ex = $exs->fetch() ?: $ex;
        }

        // ---- ১. ক্যাটাগরি-ভিত্তিক লেখার নির্দেশনা ----
        $catGuide = [
            'facebook' => "ধরন: ফেসবুক পোস্ট। কথ্য, বন্ধুসুলভ ভাষা। প্রথম লাইনেই শক্তিশালী হুক।",
            'sales'    => "ধরন: সেলস কপি। PAS বা AIDA কাঠামো। ফিচার নয়, উপকার ও আবেগ বিক্রি করো; জরুরি ভাব ও স্পষ্ট CTA রাখো।",
            'blog'     => "ধরন: ব্লগ/আর্টিকেল। আকর্ষণীয় শিরোনাম, ছোট প্যারা, সাবহেডিং, প্রতিটা পয়েন্টে উদাহরণ, উপসংহারে সারসংক্ষেপ।",
            'story'    => "ধরন: গল্প/ব্যক্তিগত অভিজ্ঞতা। কাঠামো: দৃশ্য → দ্বন্দ্ব → পরিবর্তন → উপলব্ধি। 'Show, don't tell' মানো।",
        ];
        $guide = $catGuide[$catSlug] ?? "স্বাভাবিক, আকর্ষণীয় ও অর্গানিক বাংলা কনটেন্ট লেখো।";

        // ---- ২. রিচ প্লেবুক (ফেসবুকে ভালো রিচ পাওয়ার নিয়ম) ----
        $playbook =
            "- প্রথম ১-২ লাইনেই থামাও (হুক); ভূমিকা দিও না।\n"
            . "- ছোট ছোট লাইন, মাঝে ফাঁকা লাইন — মোবাইলে পড়তে সহজ।\n"
            . "- একটাই মূল বার্তা; আবেগ বা কৌতূহল জাগাও।\n"
            . "- 'দেখাও, বলো না' — বিমূর্ত কথা নয়, বাস্তব দৃশ্য/উদাহরণ দাও।\n"
            . "- মানুষ যেন সেভ/শেয়ার করতে চায় এমন ভ্যালু দাও।\n"
            . "- অতিরিক্ত হ্যাশট্যাগ/বাইরের লিংক বডিতে দিও না; অল্প প্রাসঙ্গিক ইমোজি চলবে।";

        // ---- ৩. টোন (auto হলে র‍্যান্ডম) ----
        $toneMap = [
            'friendly' => 'আন্তরিক ও বন্ধুসুলভ', 'inspiring' => 'উদ্দীপক ও অনুপ্রেরণামূলক',
            'educational' => 'সহজ ও শিক্ষামূলক', 'funny' => 'হালকা ও একটু মজার',
            'bold' => 'আত্মবিশ্বাসী ও জোরালো',
        ];
        $toneText = $toneMap[$tone] ?? $toneMap[array_rand($toneMap)];

        // ---- ৪. দৈর্ঘ্য → নির্দেশ + max_tokens ----
        $lengthMap = [
            'short'  => ['ছোট ও ঝরঝরে, ৪-৬ লাইন (আনুমানিক ৬০-১০০ শব্দ)', 900],
            'medium' => ['মাঝারি, ৮-১২ লাইন (আনুমানিক ১৫০-২৫০ শব্দ)', 1600],
            'long'   => ['বিস্তারিত, একাধিক অনুচ্ছেদ (আনুমানিক ৩৫০-৫০০ শব্দ)', 3000],
        ];
        [$lengthText, $maxTokens] = $lengthMap[$length] ?? $lengthMap['medium'];

        // ---- ৫. লক্ষ্য ----
        $goalMap = [
            'reach'      => 'সর্বোচ্চ রিচ — ব্যাপকভাবে প্রযোজ্য, শেয়ার/সেভযোগ্য, আবেগ বা কৌতূহল ট্রিগার করে।',
            'engagement' => 'বেশি এনগেজমেন্ট — শেষে একটা সহজ প্রশ্ন রেখে কমেন্ট আহ্বান করো।',
            'sales'      => 'বিক্রি — উপকার ও জরুরি ভাব দিয়ে, স্পষ্ট অর্ডার নির্দেশনা দাও।',
            'inform'     => 'তথ্য/শিক্ষা দেওয়া — পরিষ্কার, উপকারী ও সহজবোধ্য রাখো।',
        ];
        $goalText = $goalMap[$goal] ?? $goalMap['reach'];

        // ---- ৬. হুক (সবসময় র‍্যান্ডম, বৈচিত্র্যের জন্য) ----
        $hooks = [
            'একটা কৌতূহল জাগানো প্রশ্ন', 'একটা চমকপ্রদ তথ্য বা সংখ্যা',
            'একটা ছোট বাস্তব দৃশ্য/মুহূর্ত', 'সরাসরি পাঠকের একটা ব্যথা ছুঁয়ে',
            'একটা সাহসী বা ভিন্নমতের বক্তব্য', 'একটা আপন-সম্বোধন ("আপনি কি কখনো...")',
        ];
        $hook = $hooks[array_rand($hooks)];

        // ---- Few-shot উদাহরণ ----
        $fewshot = '';
        if ($ex['example_1']) $fewshot .= "উদাহরণ ১:\n" . $ex['example_1'] . "\n\n";
        if ($ex['example_2']) $fewshot .= "উদাহরণ ২:\n" . $ex['example_2'] . "\n";

        // ---- সম্পূর্ণ system prompt একত্র করা ----
        $system =
            "তুমি একজন অভিজ্ঞ বাংলা কনটেন্ট রাইটার ও সোশ্যাল মিডিয়া স্ট্র্যাটেজিস্ট।\n\n"
            . "## ধরন\n" . $guide . "\n\n"
            . "## রিচ প্লেবুক (অবশ্যই মেনে চলো)\n" . $playbook . "\n\n"
            . ($fewshot ? "## ভালো উদাহরণ (এই মান ও ধরন অনুসরণ করো, হুবহু নকল নয়)\n" . $fewshot . "\n" : "")
            . "## এবারের নির্দেশ\n"
            . "- হুক শুরু করো: {$hook} দিয়ে।\n"
            . "- টোন: {$toneText}।\n"
            . "- দৈর্ঘ্য: {$lengthText}।\n"
            . "- লক্ষ্য: {$goalText}\n"
            . ($audience !== '' ? "- টার্গেট অডিয়েন্স: {$audience}।\n" : "")
            . ($notes !== '' ? "- অতিরিক্ত নির্দেশ: {$notes}।\n" : "")
            . "\n## যেভাবে ভাববে (এই ধাপগুলো মনে মনে করো, আউটপুটে লিখো না)\n"
            . "১. পাঠক কে ও তার সবচেয়ে বড় ব্যথা/আকাঙ্ক্ষা কী?\n"
            . "২. এই বিষয়ে সবচেয়ে তাজা ও ভিন্ন অ্যাঙ্গেল কোনটা?\n"
            . "৩. কোন হুক তাকে স্ক্রল থামাতে বাধ্য করবে?\n"
            . "এই তিনটি ভেবে, তারপর চূড়ান্ত পোস্ট লেখো।\n\n"
            . "## আউটপুট নিয়ম\n"
            . "- শুধু চূড়ান্ত পোস্টটাই দাও — কোনো ভূমিকা, ব্যাখ্যা বা ধাপ দেখিও না।\n"
            . "- গৎবাঁধা কাঠামো ও ক্লিশে ('আজ আমি বলব', 'বন্ধুরা', 'আসসালামু আলাইকুম') এড়িয়ে চলো।";

        // temperature 0.95 (বৈচিত্র্য) + দৈর্ঘ্য-অনুযায়ী max_tokens (ছোট হওয়া রোধ)
        $r = ai_chat($uid, $system, $brief, $model, 0.95, $maxTokens);
        if ($r['ok']) {
            $generated = $r['text'];
        } else {
            $error = $r['error'];
        }
    }
}

$pageTitle = 'AI লিখে দাও';
require __DIR__ . '/partials/header.php';
?>
<h3 class="mb-1">🤖 AI লিখে দাও — লেভেল ৩</h3>
<p class="text-muted">কী লিখতে চাও ও কেমন চাও বলো — AI মানুষের মতো ভেবে খসড়া বানাবে।</p>

<?php if (!ai_available($uid)): ?>
    <div class="alert alert-warning">AI ফিচার চালু নেই। আগে <a href="settings.php">সেটিংসে API key</a> দিন।</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-md-5">
            <div class="card p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">কী লিখতে হবে?</label>
                        <textarea name="brief" class="form-control" rows="4" placeholder="যেমন: হাতে বানানো মোমবাতির ফেসবুক সেলস পোস্ট, দাম ৩৯০ টাকা।" required><?= e($brief) ?></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label">ক্যাটাগরি</label>
                            <select name="category_id" class="form-select form-select-sm">
                                <option value="0">— নির্বাচন —</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">লক্ষ্য</label>
                            <select name="goal" class="form-select form-select-sm">
                                <?php foreach ($goalOptions as $k => $v): ?>
                                    <option value="<?= e($k) ?>" <?= $goal === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">টোন</label>
                            <select name="tone" class="form-select form-select-sm">
                                <?php foreach ($toneOptions as $k => $v): ?>
                                    <option value="<?= e($k) ?>" <?= $tone === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">দৈর্ঘ্য</label>
                            <select name="length" class="form-select form-select-sm">
                                <?php foreach ($lengthOptions as $k => $v): ?>
                                    <option value="<?= e($k) ?>" <?= $length === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">টার্গেট অডিয়েন্স (ঐচ্ছিক)</label>
                        <input type="text" name="audience" class="form-control form-control-sm" value="<?= e($audience) ?>" placeholder="যেমন: নতুন মা, ছাত্র, উদ্যোক্তা">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">অতিরিক্ত নির্দেশ (ঐচ্ছিক)</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="যেমন: একটা প্রশ্ন দিয়ে শেষ করো, ইমোজি কম রাখো"><?= e($notes) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">মডেল (ঐচ্ছিক)</label>
                        <select name="model" class="form-select form-select-sm">
                            <option value="">ডিফল্ট (<?= e($settings['default_model']) ?>)</option>
                            <?php foreach ($providerModels as $mo): ?>
                                <option value="<?= e($mo) ?>"><?= e($mo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">গভীর মানের জন্য Sonnet/Opus বেছে নিতে পারো।</small>
                    </div>
                    <button class="btn btn-accent w-100" id="writeBtn">✨ লিখে দাও</button>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card p-4 h-100">
                <h6 class="card-title">📄 AI-র লেখা</h6>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($generated !== ''): ?>
                    <div class="draft-output mb-2"><?= e($generated) ?></div>
                    <small class="text-muted">শব্দ সংখ্যা: <?= count_words($generated) ?></small>
                    <form method="post" action="writing_edit.php" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="prefill" value="1">
                        <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                        <input type="hidden" name="title" value="AI খসড়া">
                        <textarea name="body" class="d-none"><?= e($generated) ?></textarea>
                        <button class="btn btn-brand w-100">✏️ এডিট করে সেভ করো</button>
                    </form>
                <?php elseif (!$error): ?>
                    <p class="text-muted">বাঁ দিকে বিস্তারিত দিয়ে "লিখে দাও" চাপো — AI এখানে খসড়া দেবে।</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelector('form')?.addEventListener('submit', function () {
    const b = document.getElementById('writeBtn');
    if (b) { b.disabled = true; b.textContent = '⏳ AI ভাবছে ও লিখছে...'; }
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
