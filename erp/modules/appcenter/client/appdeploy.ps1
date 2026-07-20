<#
    ===============================================================
    App Center — Silent Install Helper (Windows)
    ===============================================================
    appdeploy:// প্রোটোকল হ্যান্ডেল করে। "Install" বাটনে ক্লিক করলে
    Windows এটি চালায়। এটি সার্ভার থেকে ইনস্টল-তথ্য নিয়ে:
      • network অ্যাপ  → নেটওয়ার্ক শেয়ারের ইনস্টলার silent চালায়
      • winget অ্যাপ   → winget install চালায়
    এবং কোন পিসি থেকে ইনস্টল হলো তা সার্ভারে লগ করে।

    সেটআপ: SETUP.md দেখুন। শুধু নিচের $ServerUrl ঠিক করে দিন
    (অথবা install-helper.bat চালালে নিজে থেকেই বসে যায়)।
#>

param([string]$Uri)

# ---- আপনার সার্ভারের ঠিকানা ----
$ServerUrl = "http://YOUR-SERVER/erp"     # যেমন http://192.168.0.10/erp

$LogFile = Join-Path $env:TEMP "appdeploy.log"
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m" | Out-File -Append -FilePath $LogFile -Encoding utf8 }

$logId = 0
$api   = "$ServerUrl/modules/appcenter/api.php"

try {
    Log "Called with URI: $Uri"

    # appdeploy://<id>?t=<token>&a=<action>  থেকে id, token, action
    $match = [regex]::Match($Uri, 'appdeploy://(?<id>\d+)\?t=(?<t>[a-fA-F0-9]+)(&a=(?<a>\w+))?')
    if (-not $match.Success) { throw "URI ফরম্যাট ঠিক নয়।" }
    $id     = $match.Groups['id'].Value
    $token  = $match.Groups['t'].Value
    $action = if ($match.Groups['a'].Success) { $match.Groups['a'].Value } else { 'install' }
    $pc     = $env:COMPUTERNAME

    # সার্ভার থেকে তথ্য (পিসির নাম ও অ্যাকশনসহ, যা লগ হবে)
    $info = Invoke-RestMethod -Uri "$api?id=$id&t=$token&a=$action&pc=$pc" -TimeoutSec 20
    if (-not $info.ok) { throw "সার্ভার বলছে: $($info.error)" }
    $logId = $info.log_id
    Log "App: $($info.name) | Type: $($info.type) | Action: $action | PC: $pc | log_id: $logId"

    # -------- অ্যাকশন চালানো --------
    if ($info.type -eq 'winget') {
        # Windows Package Manager: install / upgrade / uninstall
        $common = "--silent --accept-package-agreements --accept-source-agreements"
        switch ($action) {
            'update'    { $wingetArgs = "upgrade   --id `"$($info.winget_id)`" $common" }
            'uninstall' { $wingetArgs = "uninstall --id `"$($info.winget_id)`" --silent" }
            default     { $wingetArgs = "install   --id `"$($info.winget_id)`" $common" }
        }
        Log "Running: winget $wingetArgs"
        $p = Start-Process -FilePath "winget" -ArgumentList $wingetArgs -Wait -PassThru -WindowStyle Hidden
        if ($p.ExitCode -ne 0) { throw "winget exit code: $($p.ExitCode)" }
    }
    else {
        # নেটওয়ার্ক শেয়ারের ইনস্টলার (শুধু install)
        if ($action -ne 'install') { throw "network অ্যাপে $action সম্ভব নয়।" }
        $path = $info.path
        if (-not (Test-Path $path)) { throw "ইনস্টলার ফাইল পাওয়া যায়নি: $path" }
        if ([string]::IsNullOrWhiteSpace($info.args)) {
            $p = Start-Process -FilePath $path -Wait -PassThru
        } else {
            $p = Start-Process -FilePath $path -ArgumentList $info.args -Wait -PassThru
        }
        if ($p.ExitCode -ne 0) { throw "installer exit code: $($p.ExitCode)" }
    }

    # সফল — সার্ভারে জানানো
    Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$logId; status='success' } -TimeoutSec 15 | Out-Null
    Log "SUCCESS: $($info.name)"
}
catch {
    Log "ERROR: $($_.Exception.Message)"
    # ব্যর্থ — সার্ভারে জানানো (সম্ভব হলে)
    try {
        if ($logId -gt 0) {
            Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$logId; status='failed'; note=$_.Exception.Message } -TimeoutSec 15 | Out-Null
        }
    } catch {}
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show(
        "ইনস্টল করা যায়নি।`n`n$($_.Exception.Message)`n`nবিস্তারিত: $LogFile",
        "App Center", 'OK', 'Error') | Out-Null
}
