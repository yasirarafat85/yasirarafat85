<#
    ===============================================================
    App Center — Worker (আসল কাজ যে করে)
    ===============================================================
    এটি elevated context-এ চলে (SYSTEM Scheduled Task বা admin account),
    তাই স্ট্যান্ডার্ড ইউজারের পিসিতেও admin ছাড়াই install/update/uninstall
    করতে পারে — ঠিক Windows Update-এর SYSTEM সার্ভিসের মতো।

    কাজ: queue ফোল্ডারের অনুরোধ পড়ে → সার্ভার API থেকে তথ্য নেয় →
         winget/installer চালায় → ফলাফল সার্ভারে লগ করে।

    সরাসরি চালাবেন না — appdeploy.ps1 (dispatcher) বা Scheduled Task
    এটিকে ডাকে। কনফিগ আসে পাশের config.json থেকে।
#>
param(
    [switch]$ProcessQueue,        # queue-এর সব অনুরোধ প্রসেস করো
    [string]$RequestFile          # অথবা নির্দিষ্ট একটি অনুরোধ ফাইল
)

$base   = $PSScriptRoot
$logF   = Join-Path $base 'worker.log'
$queue  = Join-Path $base 'queue'
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  [worker] $m" | Out-File -Append -FilePath $logF -Encoding utf8 }

# সার্ভার ঠিকানা config.json থেকে
try {
    $cfg    = Get-Content (Join-Path $base 'config.json') -Raw | ConvertFrom-Json
    $server = $cfg.server.TrimEnd('/')
} catch {
    Log "config.json পড়া যায়নি: $($_.Exception.Message)"; return
}
$api = "$server/modules/appcenter/api.php"

function Invoke-Request($file) {
    $id=''; $token=''; $action='install'; $logId=0
    try {
        $r      = Get-Content $file -Raw | ConvertFrom-Json
        $id     = $r.id; $token = $r.token
        $action = if ($r.action) { $r.action } else { 'install' }
        $pc     = $env:COMPUTERNAME

        $info = Invoke-RestMethod -Uri "$api?id=$id&t=$token&a=$action&pc=$pc" -TimeoutSec 20
        if (-not $info.ok) { throw "সার্ভার বলছে: $($info.error)" }
        $logId = $info.log_id
        Log "App=$($info.name) Type=$($info.type) Action=$action PC=$pc log=$logId"

        if ($info.type -eq 'winget') {
            $common = "--silent --accept-package-agreements --accept-source-agreements"
            switch ($action) {
                'update'    { $wa = "upgrade   --id `"$($info.winget_id)`" $common" }
                'uninstall' { $wa = "uninstall --id `"$($info.winget_id)`" --silent" }
                default     { $wa = "install   --id `"$($info.winget_id)`" $common" }
            }
            Log "winget $wa"
            $p = Start-Process -FilePath "winget" -ArgumentList $wa -Wait -PassThru -WindowStyle Hidden
            if ($p.ExitCode -ne 0) { throw "winget exit code: $($p.ExitCode)" }
        }
        else {
            if ($action -ne 'install') { throw "network অ্যাপে $action সম্ভব নয়।" }
            if (-not (Test-Path $info.path)) { throw "ইনস্টলার পাওয়া যায়নি: $($info.path)" }
            if ([string]::IsNullOrWhiteSpace($info.args)) {
                $p = Start-Process -FilePath $info.path -Wait -PassThru
            } else {
                $p = Start-Process -FilePath $info.path -ArgumentList $info.args -Wait -PassThru
            }
            if ($p.ExitCode -ne 0) { throw "installer exit code: $($p.ExitCode)" }
        }

        Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$logId; status='success' } -TimeoutSec 15 | Out-Null
        Log "SUCCESS: $($info.name)"
    }
    catch {
        Log "ERROR: $($_.Exception.Message)"
        try {
            if ($logId -gt 0) {
                Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$logId; status='failed'; note=$_.Exception.Message } -TimeoutSec 15 | Out-Null
            }
        } catch {}
    }
    finally {
        Remove-Item $file -Force -ErrorAction SilentlyContinue
    }
}

if ($RequestFile) {
    Invoke-Request $RequestFile
}
elseif ($ProcessQueue) {
    if (Test-Path $queue) {
        Get-ChildItem $queue -Filter *.json -ErrorAction SilentlyContinue |
            Sort-Object CreationTime | ForEach-Object { Invoke-Request $_.FullName }
    }
}
