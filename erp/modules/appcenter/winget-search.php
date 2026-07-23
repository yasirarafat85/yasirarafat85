<?php
/**
 * winget-search.php — সার্ভারে `winget search` চালিয়ে ফলাফল দেয় (JSON)।
 * winget সার্ভারে না থাকলে/না চললে পরিষ্কার বার্তা দেয় (তখন তালিকা থেকে
 * বা ম্যানুয়ালি ID দেওয়া যাবে)।
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requirePermission('appcenter.manage');
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) input('q', ''));
if ($q === '') {
    echo json_encode(['ok' => false, 'error' => 'কিছু টাইপ করুন।']);
    exit;
}

// exec চালু আছে কিনা
if (!function_exists('exec')) {
    echo json_encode(['ok' => false, 'error' => 'সার্ভারে exec বন্ধ — তালিকা থেকে বাছুন বা ID দিন।']);
    exit;
}

// winget.exe খুঁজি (PATH-এ না থাকলে সাধারণ জায়গায়)
$winget = 'winget';
$candidates = glob('C:\\Program Files\\WindowsApps\\Microsoft.DesktopAppInstaller_*\\winget.exe') ?: [];
if ($candidates) { $winget = '"' . end($candidates) . '"'; }

$cmd = $winget . ' search --query ' . escapeshellarg($q)
     . ' --accept-source-agreements --disable-interactivity 2>&1';
$out = [];
$ret = null;
@exec($cmd, $out, $ret);

if (!$out || $ret === 127) {
    echo json_encode(['ok' => false, 'error' => 'winget সার্ভারে চলছে না — নিচের তালিকা থেকে বাছুন বা ID দিন।']);
    exit;
}

// winget টেবিল আউটপুট পার্স: header লাইন থেকে কলামের অবস্থান বের করি
$results = [];
$nameCol = $idCol = $verCol = $srcCol = -1;
foreach ($out as $line) {
    $plain = trim($line);
    if ($plain === '' || strpos($plain, '---') === 0) continue;

    // header খুঁজি
    if ($idCol < 0) {
        if (preg_match('/\bName\b/', $line) && preg_match('/\bId\b/', $line)) {
            $nameCol = strpos($line, 'Name');
            $idCol   = strpos($line, 'Id');
            $verCol  = strpos($line, 'Version');
            $srcCol  = strpos($line, 'Source');
        }
        continue;
    }

    // ডেটা রো
    $name = trim(mb_substr($line, $nameCol, ($idCol - $nameCol)));
    $id   = trim(mb_substr($line, $idCol, ($verCol > $idCol ? $verCol - $idCol : null)));
    $ver  = $verCol > 0 ? trim(mb_substr($line, $verCol, ($srcCol > $verCol ? $srcCol - $verCol : null))) : '';
    // id-তে স্পেস থাকলে (কলাম মেলেনি) — প্রথম টোকেন নিই
    if (strpos($id, ' ') !== false) { $id = preg_split('/\s+/', $id)[0]; }
    if ($id === '' || $name === '') continue;
    $results[] = ['name' => $name, 'id' => $id, 'version' => $ver];
    if (count($results) >= 15) break;
}

if (!$results) {
    echo json_encode(['ok' => false, 'error' => 'ফলাফল পার্স করা গেল না — তালিকা/ID ব্যবহার করুন।']);
    exit;
}

echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
