<?php
/**
 * winget-run.php — winget.run-এর public API-তে সার্চ করে ফলাফল দেয় (JSON)।
 * সার্ভার প্রক্সি করে বলে ব্রাউজারে CORS সমস্যা হয় না। সার্ভারে ইন্টারনেট
 * না থাকলে/API বদলালে পরিষ্কার বার্তা দেয় (তখন তালিকা বা ম্যানুয়াল ID)।
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requirePermission('appcenter.manage');
header('Content-Type: application/json; charset=utf-8');

/** URL fetch — curl থাকলে curl, নইলে file_get_contents */
function fetch_url(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'AppCenter/1.0',
            // XAMPP-এ প্রায়ই CA বান্ডল থাকে না; এটা শুধু পাবলিক read-only API,
            // তাই verify বন্ধ রাখা হলো যাতে নির্ভরযোগ্যভাবে চলে।
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $r    = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($r !== false && $code >= 200 && $code < 300) ? $r : null;
    }
    $ctx = stream_context_create([
        'http' => ['timeout' => 12, 'header' => "User-Agent: AppCenter/1.0\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $r = @file_get_contents($url, false, $ctx);
    return $r === false ? null : $r;
}

$q = trim((string) input('q', ''));
if ($q === '') {
    echo json_encode(['ok' => false, 'error' => 'কিছু টাইপ করুন।']);
    exit;
}

$url  = 'https://api.winget.run/v2/packages?query=' . urlencode($q) . '&take=15&order=desc';
$json = fetch_url($url);

if ($json === null) {
    echo json_encode(['ok' => false, 'error' => 'winget.run-এ সংযোগ ব্যর্থ — সার্ভারে ইন্টারনেট আছে কিনা দেখুন, বা তালিকা/ম্যানুয়াল ID ব্যবহার করুন।']);
    exit;
}

$data = json_decode($json, true);
$packages = $data['Packages'] ?? $data['packages'] ?? null;
if (!is_array($packages)) {
    echo json_encode(['ok' => false, 'error' => 'winget.run থেকে ফলাফল পড়া গেল না (API বদলে থাকতে পারে)।']);
    exit;
}

$results = [];
foreach ($packages as $p) {
    $id = $p['Id'] ?? ($p['id'] ?? '');
    if ($id === '') continue;
    $latest = $p['Latest'] ?? [];
    $results[] = [
        'id'        => $id,
        'name'      => $latest['Name'] ?? $id,
        'publisher' => $latest['Publisher'] ?? '',
        'version'   => (isset($p['Versions'][0]) ? $p['Versions'][0] : ($latest['Version'] ?? '')),
    ];
    if (count($results) >= 15) break;
}

if (!$results) {
    echo json_encode(['ok' => false, 'error' => 'কিছু পাওয়া যায়নি — অন্য নামে চেষ্টা করুন।']);
    exit;
}

echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
