<?php
/**
 * ---------------------------------------------------------------
 *  ERP Foundation - Main Configuration
 * ---------------------------------------------------------------
 *  এখানে ডাটাবেস ও অ্যাপের মূল সেটিংস থাকবে।
 *  নতুন সার্ভারে চালানোর সময় শুধু এই ফাইলটা এডিট করলেই হবে।
 */

// ---- Database Settings (আপনার সার্ভার অনুযায়ী পরিবর্তন করুন) ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'erp_foundation');
define('DB_USER', 'root');
define('DB_PASS', '');       // XAMPP-এ সাধারণত খালি
define('DB_CHARSET', 'utf8mb4');

// ---- Application Settings ----
define('APP_NAME', 'IT Admin ERP');

/**
 * ---- App Center (সফটওয়্যার ডিপ্লয়) সেটিংস ----
 *
 * NETWORK_SHARE_BASE:
 *   যে নেটওয়ার্ক ফোল্ডারে সব ইনস্টলার রাখা আছে তার বেস UNC পাথ।
 *   অ্যাডমিন প্যানেলে অ্যাপের relative path দিলে এর সাথে জোড়া লাগবে।
 *   উদাহরণ: '\\\\FILESERVER\\Software'  (Windows share)
 *   অ্যাপে full path (\\... দিয়ে শুরু) দিলে সেটাই সরাসরি ব্যবহার হবে।
 */
define('NETWORK_SHARE_BASE', '\\\\FILESERVER\\Software');

/**
 * APP_SECRET:
 *   silent-install টোকেন সই করতে ব্যবহৃত গোপন কী।
 *   ⚠️ লাইভে যাওয়ার আগে এটি একটি লম্বা random স্ট্রিং দিয়ে বদলান।
 */
define('APP_SECRET', 'change-this-to-a-long-random-secret-string');

/**
 * BASE_URL — ব্রাউজারে অ্যাপের ঠিকানা।
 * নিচের কোড নিজে থেকে ঠিক করে নেবে (localhost/hosting দুটোতেই কাজ করবে)।
 */
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
// core/ বা modules/... থেকে চললেও রুট ঠিক রাখার জন্য:
$root = preg_replace('#/(core|includes|modules|auth)(/.*)?$#', '', $scriptDir);
$root = rtrim($root, '/');
define('BASE_URL', $root === '' ? '/' : $root . '/');

// ---- Timezone ----
date_default_timezone_set('Asia/Dhaka');

// ---- Error Reporting (development-এ true, live-এ false) ----
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
