<#
    ===============================================================
    App Center — Dispatcher (appdeploy:// প্রোটোকল হ্যান্ডলার)
    ===============================================================
    "Install/Update/Uninstall" বাটনে ক্লিক করলে Windows এটি চালায়।
    এটি হালকা — শুধু অনুরোধটা queue-তে লেখে, তারপর ঠিক করে কীভাবে
    elevated করে চালাবে (স্ট্যান্ডার্ড ইউজারেও যেন admin ছাড়া চলে):

      ১. AppDeployRunner টাস্ক আছে?  → সেটা চালাও (SYSTEM/admin — নিরাপদ)
      ২. নাহলে                        → এখানেই worker চালাও (admin/user-scope হলে)

    কনফিগ (সার্ভার ঠিকানা) আসে পাশের config.json থেকে; না থাকলে নিচের
    $FallbackServer ব্যবহার হয় (install-helper.bat এটি বসিয়ে দেয়)।
#>
param([string]$Uri)

$FallbackServer = "http://YOUR-SERVER/erp"   # bat/সেটআপ এটি বসায় বা config.json ব্যবহার হয়

$base  = $PSScriptRoot
$logF  = Join-Path $env:TEMP 'appdeploy.log'
$queue = Join-Path $base 'queue'
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  [disp] $m" | Out-File -Append -FilePath $logF -Encoding utf8 }

try {
    Log "URI: $Uri"

    # appdeploy://<id>?t=<token>&a=<action>
    $m = [regex]::Match($Uri, 'appdeploy://(?<id>\d+)\?t=(?<t>[a-fA-F0-9]+)(&a=(?<a>\w+))?')
    if (-not $m.Success) { throw "URI ফরম্যাট ঠিক নয়।" }
    $id     = $m.Groups['id'].Value
    $token  = $m.Groups['t'].Value
    $action = if ($m.Groups['a'].Success) { $m.Groups['a'].Value } else { 'install' }

    # queue-তে অনুরোধ লিখি
    New-Item -ItemType Directory -Force -Path $queue | Out-Null
    $reqFile = Join-Path $queue ("{0}.json" -f ([guid]::NewGuid().ToString('N')))
    @{ id = $id; token = $token; action = $action } | ConvertTo-Json -Compress | Set-Content -Path $reqFile -Encoding utf8
    Log "Queued: id=$id action=$action file=$reqFile"

    # config.json না থাকলে বানিয়ে দিই (fallback server দিয়ে) — worker এটি পড়ে
    $cfgPath = Join-Path $base 'config.json'
    if (-not (Test-Path $cfgPath)) {
        @{ server = $FallbackServer.TrimEnd('/') } | ConvertTo-Json | Set-Content -Path $cfgPath -Encoding utf8
    }

    # ---- elevation routing ----
    $null = & schtasks.exe /query /tn "AppDeployRunner" 2>$null
    if ($LASTEXITCODE -eq 0) {
        Log "Runner task পাওয়া গেছে → trigger"
        & schtasks.exe /run /tn "AppDeployRunner" | Out-Null
    }
    else {
        Log "Runner task নেই → সরাসরি worker চালাই (current context)"
        & (Join-Path $base 'appdeploy-worker.ps1') -ProcessQueue
    }
}
catch {
    Log "DISPATCH ERROR: $($_.Exception.Message)"
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show(
        "অনুরোধ পাঠানো যায়নি।`n`n$($_.Exception.Message)`n`nলগ: $logF",
        "App Center", 'OK', 'Error') | Out-Null
}
