<?php
// includes/db.php — SQLite কানেকশন, টেবিল অটো-তৈরি ও প্রথমবার ডেটা seed করা

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // data ফোল্ডার না থাকলে তৈরি করা
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $firstRun = !file_exists(DB_PATH);

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    create_tables($pdo);
    if ($firstRun) {
        seed_data($pdo);
    }

    return $pdo;
}

function create_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT,
            tips TEXT,
            example_1 TEXT,
            example_2 TEXT
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            formula TEXT,
            structure TEXT NOT NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS writings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            word_count INTEGER DEFAULT 0,
            ai_feedback TEXT,
            ai_score INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (category_id) REFERENCES categories(id)
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            provider TEXT,
            api_key TEXT,
            default_model TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
}

function seed_data(PDO $pdo): void
{
    // ---- ৪টা ক্যাটাগরি (লেভেল ১: শেখার কনটেন্ট) ----
    $categories = [
        [
            'name' => 'ফেসবুক পোস্ট',
            'slug' => 'facebook',
            'description' => 'ছোট, শক্তিশালী, স্ক্রল থামানো লেখা।',
            'tips' => "১. প্রথম ১-২ লাইনেই 'হুক' দাও — ভূমিকা বাদ দাও।\n২. পাঠকের ব্যথা বা স্বপ্ন ছুঁয়ে দাও।\n৩. কৌতূহল তৈরি করো, পুরোটা বলে দিও না।\n৪. শেষে একটা স্পষ্ট কল-টু-অ্যাকশন রাখো।",
            'example_1' => "৩ মাস আগেও একটা লাইন লিখতে পারতাম না।\nআজ লিখেই মাসে টাকা আয় করছি।\nকীভাবে? গল্পটা বলি 👇",
            'example_2' => "লিখতে বসলেই মাথা ফাঁকা হয়ে যায়?\nএই একটা কৌশল আমার সব বদলে দিয়েছিল।\nকমেন্টে 'শিখব' লিখুন, ফ্রি গাইড পাঠাব।",
        ],
        [
            'name' => 'সেলস কপি',
            'slug' => 'sales',
            'description' => 'ফিচার নয়, পাঠকের সমস্যা ও স্বপ্ন বিক্রি করা।',
            'tips' => "১. PAS ফর্মুলা: Problem → Agitate → Solution।\n২. 'আমাদের পণ্য ভালো' নয় — 'তোমার জীবন কীভাবে বদলাবে' লেখো।\n৩. একটা জরুরি ভাব (urgency) যোগ করো।\n৪. স্পষ্ট অর্ডার নির্দেশনা দাও।",
            'example_1' => "ঘরে সুন্দর গন্ধ থাকে না? 😔\nদামি ফ্রেশনার কিনেও গন্ধ এক ঘণ্টায় শেষ।\nহাতে বানানো মোমবাতি — প্রাকৃতিক সুবাস, ৮ ঘণ্টা জ্বলে।\nআজই অর্ডার করুন! 🕯️",
            'example_2' => "চুল পড়া নিয়ে চিন্তিত?\nবাজারের কেমিক্যাল প্রোডাক্ট আরও ক্ষতি করছে।\nআমাদের হারবাল তেল — ১ মাসে দৃশ্যমান পরিবর্তন।\nসীমিত স্টক, এখনই নিন।",
        ],
        [
            'name' => 'ব্লগ / আর্টিকেল',
            'slug' => 'blog',
            'description' => 'পাঠককে শেষ পর্যন্ত ধরে রাখা ও বিশ্বাস তৈরি করা।',
            'tips' => "১. শিরোনামে সংখ্যা + উপকার রাখো।\n২. একটা প্যারা = একটা আইডিয়া।\n৩. প্রতিটা পয়েন্টে একটা বাস্তব উদাহরণ দাও।\n৪. ভূমিকায় একটা প্রতিশ্রুতি দাও, উপসংহারে সেটা পূরণ করো।",
            'example_1' => "শিরোনাম: ৭ দিনে প্রথম আর্টিকেল লেখার সহজ ৫টা ধাপ\n\nলিখতে চান কিন্তু জানেন না কোথা থেকে শুরু করবেন? এই লেখায় ঠিক ৫টা ধাপ পাবেন, যেগুলো আজ থেকেই কাজে লাগাতে পারবেন।",
            'example_2' => "শিরোনাম: যে ৩টা ভুলে আপনার লেখা কেউ পড়ে না\n\nআপনি হয়তো ভালো লিখছেন, তবু সাড়া পাচ্ছেন না। কারণ এই সাধারণ ভুলগুলো। চলুন এক এক করে ঠিক করি।",
        ],
        [
            'name' => 'গল্প / ব্যক্তিগত অভিজ্ঞতা',
            'slug' => 'story',
            'description' => 'পাঠকের অনুভূতি স্পর্শ করা — সবচেয়ে শক্তিশালী লেখা।',
            'tips' => "১. ফর্মুলা: দৃশ্য → দ্বন্দ্ব → পরিবর্তন → উপলব্ধি।\n২. 'Show, don't tell' — অনুভূতির নাম বলো না, দেখাও।\n৩. ছোট বাক্য, বাস্তব বিবরণ দাও।\n৪. শেষে একটা উপলব্ধি বা শিক্ষা রাখো।",
            'example_1' => "মোবাইলের স্ক্রিনে ৩ ঘণ্টা ধরে একটা লাইনও লিখতে পারিনি।\nকার্সরটা শুধু জ্বলছিল আর নিভছিল।\nতারপর একটা কথা মনে পড়ল — 'একজনের জন্য লেখো'।\nসেই রাতেই প্রথম লেখাটা শেষ করলাম।",
            'example_2' => "বাবা বলতেন, 'যা পারিস না, তা নিয়ে বড় কথা বলিস না।'\nআমি লিখতে পারতাম না, তাই চুপ থাকতাম।\nআজ যখন আমার লেখা কেউ শেয়ার করে,\nমনে হয় বাবাকে ভুল প্রমাণ করতে পেরেছি।",
        ],
    ];

    $catStmt = $pdo->prepare("
        INSERT INTO categories (name, slug, description, tips, example_1, example_2)
        VALUES (:name, :slug, :description, :tips, :example_1, :example_2)
    ");
    $catIds = [];
    foreach ($categories as $c) {
        $catStmt->execute($c);
        $catIds[$c['slug']] = (int)$pdo->lastInsertId();
    }

    // ---- টেমপ্লেট (লেভেল ২: ঘর পূরণ → খসড়া) ----
    // structure-এ {ঘরের_নাম} প্লেসহোল্ডার থাকবে, PHP পরে সেগুলো বসাবে।
    $templates = [
        // ফেসবুক পোস্ট
        [$catIds['facebook'], 'হুক + গল্প', 'Hook-Story-CTA',
            "{হুক}\n\n{গল্প}\n\n👉 {কল_টু_অ্যাকশন}"],
        [$catIds['facebook'], 'প্রশ্ন দিয়ে শুরু', 'Question-Value-CTA',
            "{প্রশ্ন}\n\n{সমাধান}\n\nআগ্রহী হলে {কল_টু_অ্যাকশন}"],
        // সেলস কপি
        [$catIds['sales'], 'PAS ফর্মুলা', 'Problem-Agitate-Solution',
            "{সমস্যা} 😔\n{খোঁচা}\nনিয়ে এসেছি {পণ্য} — {উপকার}।\nআজই অর্ডার করুন! ✅"],
        [$catIds['sales'], 'AIDA ফর্মুলা', 'Attention-Interest-Desire-Action',
            "{আকর্ষণ}\n{আগ্রহ}\n{ইচ্ছা}\n👉 {পদক্ষেপ}"],
        // ব্লগ
        [$catIds['blog'], 'তালিকা আর্টিকেল', 'Listicle',
            "শিরোনাম: {সংখ্যা}টি {বিষয়} যা {উপকার}\n\nভূমিকা: {প্রতিশ্রুতি}\n\nমূল পয়েন্ট: {পয়েন্টসমূহ}\n\nউপসংহার: {সারসংক্ষেপ}"],
        [$catIds['blog'], 'সমস্যা-সমাধান', 'Problem-Solution',
            "শিরোনাম: {সমস্যা} — সমাধান যেভাবে\n\nভূমিকা: {প্রেক্ষাপট}\n\nসমাধান: {ধাপসমূহ}\n\nউপসংহার: {পরামর্শ}"],
        // গল্প
        [$catIds['story'], 'পরিবর্তনের গল্প', 'Scene-Conflict-Change-Insight',
            "{দৃশ্য}\n{দ্বন্দ্ব}\n{পরিবর্তন}\n{উপলব্ধি}"],
        [$catIds['story'], 'শিক্ষণীয় স্মৃতি', 'Memory-Lesson',
            "একদিন {স্মৃতি}।\nতখন বুঝিনি, কিন্তু {ঘটনা}।\nআজ বুঝি — {শিক্ষা}।"],
    ];

    $tplStmt = $pdo->prepare("
        INSERT INTO templates (category_id, name, formula, structure)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($templates as $t) {
        $tplStmt->execute($t);
    }
}
