<#
    ===============================================================
    App Center — Elevation Setup (admin একবার চালাবে)
    ===============================================================
    স্ট্যান্ডার্ড ইউজারের পিসিতে admin ছাড়াই install/update/uninstall
    করাতে এটি একটি privileged "AppDeployRunner" Scheduled Task বানায়,
    যা স্ট্যান্ডার্ড ইউজার শুধু trigger করতে পারবে কিন্তু চলবে elevated হয়ে।

    ব্যবহার (PowerShell, "Run as administrator"):

      # পদ্ধতি ১ — SYSTEM হিসেবে (সুপারিশ; কোনো পাসওয়ার্ড লাগে না)
      .\setup-elevation.ps1 -Server "http://192.168.0.10/erp" -RunAs system

      # পদ্ধতি ২ — আপনার admin অ্যাকাউন্টে (Windows পাসওয়ার্ড নিরাপদে রাখে)
      .\setup-elevation.ps1 -Server "http://192.168.0.10/erp" -RunAs admin

    দুই পদ্ধতিই সাপোর্টেড — পিসিভেদে যেটা দরকার সেটা ব্যবহার করুন।
    কোনো ক্ষেত্রেই প্লেইন পাসওয়ার্ড ডিস্কে থাকে না।
#>
param(
    [Parameter(Mandatory)][string]$Server,
    [ValidateSet('system','admin')][string]$RunAs = 'system'
)

# admin কিনা যাচাই
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
           ).IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)
if (-not $isAdmin) { Write-Host "❌ এই স্ক্রিপ্ট 'Run as administrator' দিয়ে চালান।" -ForegroundColor Red; exit 1 }

$dest  = "$env:ProgramData\AppDeploy"
$queue = Join-Path $dest 'queue'
New-Item -ItemType Directory -Force -Path $dest, $queue | Out-Null

Write-Host "[1/4] স্ক্রিপ্ট কপি → $dest"
Copy-Item (Join-Path $PSScriptRoot 'appdeploy.ps1')        $dest -Force
Copy-Item (Join-Path $PSScriptRoot 'appdeploy-worker.ps1') $dest -Force
@{ server = $Server.TrimEnd('/') } | ConvertTo-Json | Set-Content (Join-Path $dest 'config.json') -Encoding utf8

Write-Host "[2/4] appdeploy:// প্রোটোকল রেজিস্টার (সব ইউজারের জন্য, HKLM)"
$cmd = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$dest\appdeploy.ps1`" `"%1`""
New-Item -Path 'HKLM:\SOFTWARE\Classes\appdeploy\shell\open\command' -Force | Out-Null
Set-ItemProperty 'HKLM:\SOFTWARE\Classes\appdeploy' -Name '(default)'    -Value 'URL:App Center Deploy Protocol'
Set-ItemProperty 'HKLM:\SOFTWARE\Classes\appdeploy' -Name 'URL Protocol' -Value ''
Set-ItemProperty 'HKLM:\SOFTWARE\Classes\appdeploy\shell\open\command' -Name '(default)' -Value $cmd

Write-Host "[3/4] AppDeployRunner টাস্ক তৈরি ($RunAs হিসেবে)"
$taskAction = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$dest\appdeploy-worker.ps1`" -ProcessQueue"
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)

if ($RunAs -eq 'system') {
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Register-ScheduledTask -TaskName 'AppDeployRunner' -Action $taskAction -Principal $principal -Settings $settings -Force | Out-Null
}
else {
    $cred = Get-Credential -Message 'যে admin অ্যাকাউন্টে টাস্ক চলবে তার ইউজার ও পাসওয়ার্ড দিন'
    Register-ScheduledTask -TaskName 'AppDeployRunner' -Action $taskAction -Settings $settings `
        -User $cred.UserName -Password $cred.GetNetworkCredential().Password -RunLevel Highest -Force | Out-Null
}

Write-Host "[4/4] স্ট্যান্ডার্ড ইউজারদের টাস্ক 'run' করার অনুমতি দেওয়া"
# টাস্কের DACL-এ Authenticated Users-কে Read+Execute (GRGX) দিই, যাতে ইউজার trigger করতে পারে
try {
    $svc = New-Object -ComObject 'Schedule.Service'; $svc.Connect()
    $folder = $svc.GetFolder('\')
    $task = $folder.GetTask('AppDeployRunner')
    $sddl = $task.GetSecurityDescriptor(0xF)          # DACL_SECURITY_INFORMATION
    if ($sddl -notmatch 'AU') {
        $sddl += '(A;;GRGX;;;AU)'                       # Authenticated Users: read+execute
        $task.SetSecurityDescriptor($sddl, 0)
    }
    Write-Host "     ✅ অনুমতি সেট হয়েছে"
} catch {
    Write-Host "     ⚠️ DACL সেট করা যায়নি: $($_.Exception.Message)" -ForegroundColor Yellow
    Write-Host "        দরকার হলে ম্যানুয়ালি Task Scheduler-এ Users-কে run অনুমতি দিন।"
}

Write-Host ""
Write-Host "===============================================" -ForegroundColor Green
Write-Host "  ✅ Elevation setup সম্পন্ন ($RunAs মোড)" -ForegroundColor Green
Write-Host "===============================================" -ForegroundColor Green
Write-Host "  ফোল্ডার : $dest"
Write-Host "  সার্ভার : $Server"
Write-Host "  এখন স্ট্যান্ডার্ড ইউজারও ড্যাশবোর্ড থেকে admin ছাড়াই"
Write-Host "  Install / Update / Uninstall করতে পারবে।"
