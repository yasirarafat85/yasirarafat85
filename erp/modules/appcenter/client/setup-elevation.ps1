<#
    ===============================================================
    App Center - Elevation Setup (admin runs once)
    ===============================================================
    Lets standard users install/update/uninstall without admin, by
    creating a privileged "AppDeployRunner" scheduled task that a
    standard user can only trigger but which runs elevated.

    Usage (PowerShell, "Run as administrator"):

      # Option 1 - as SYSTEM (recommended; no password needed)
      .\setup-elevation.ps1 -Server "http://192.168.0.10/erp" -RunAs system

      # Option 2 - as your admin account (Windows stores the cred safely)
      .\setup-elevation.ps1 -Server "http://192.168.0.10/erp" -RunAs admin

    Both modes are supported - use whichever a given PC needs. No
    plaintext password is stored on disk in either mode.

    NOTE: keep this file ASCII-only so Windows PowerShell 5.1 parses
    it correctly regardless of file encoding.
#>
param(
    [Parameter(Mandatory)][string]$Server,
    [ValidateSet('system','admin')][string]$RunAs = 'system'
)

# require admin
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
           ).IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)
if (-not $isAdmin) { Write-Host "ERROR: run this script as administrator." -ForegroundColor Red; exit 1 }

$dest  = "$env:ProgramData\AppDeploy"
$queue = Join-Path $dest 'queue'
New-Item -ItemType Directory -Force -Path $dest, $queue | Out-Null

Write-Host "[1/4] Copy scripts -> $dest"
Copy-Item (Join-Path $PSScriptRoot 'appdeploy.ps1')        $dest -Force
Copy-Item (Join-Path $PSScriptRoot 'appdeploy-worker.ps1') $dest -Force
@{ server = $Server.TrimEnd('/') } | ConvertTo-Json | Set-Content (Join-Path $dest 'config.json') -Encoding utf8

Write-Host "[2/4] Register appdeploy:// protocol (all users, HKLM)"
$cmd = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$dest\appdeploy.ps1`" `"%1`""
New-Item -Path 'HKLM:\SOFTWARE\Classes\appdeploy\shell\open\command' -Force | Out-Null
Set-ItemProperty 'HKLM:\SOFTWARE\Classes\appdeploy' -Name '(default)'    -Value 'URL:App Center Deploy Protocol'
Set-ItemProperty 'HKLM:\SOFTWARE\Classes\appdeploy' -Name 'URL Protocol' -Value ''
Set-ItemProperty 'HKLM:\SOFTWARE\Classes\appdeploy\shell\open\command' -Name '(default)' -Value $cmd

Write-Host "[3/4] Create AppDeployRunner task (as $RunAs)"
$taskAction = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$dest\appdeploy-worker.ps1`" -ProcessQueue"
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)

if ($RunAs -eq 'system') {
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Register-ScheduledTask -TaskName 'AppDeployRunner' -Action $taskAction -Principal $principal -Settings $settings -Force | Out-Null
}
else {
    $cred = Get-Credential -Message 'Admin account the task will run as (user and password)'
    Register-ScheduledTask -TaskName 'AppDeployRunner' -Action $taskAction -Settings $settings `
        -User $cred.UserName -Password $cred.GetNetworkCredential().Password -RunLevel Highest -Force | Out-Null
}

Write-Host "[4/4] Allow standard users to run the task"
# grant Authenticated Users read+execute (GRGX) on the task DACL so users can trigger it
try {
    $svc = New-Object -ComObject 'Schedule.Service'; $svc.Connect()
    $folder = $svc.GetFolder('\')
    $task = $folder.GetTask('AppDeployRunner')
    $sddl = $task.GetSecurityDescriptor(0xF)          # DACL_SECURITY_INFORMATION
    if ($sddl -notmatch 'AU') {
        $sddl += '(A;;GRGX;;;AU)'                       # Authenticated Users: read+execute
        $task.SetSecurityDescriptor($sddl, 0)
    }
    Write-Host "     OK: permission set"
} catch {
    Write-Host "     WARN: could not set DACL: $($_.Exception.Message)" -ForegroundColor Yellow
    Write-Host "     If needed, grant Users 'run' on AppDeployRunner in Task Scheduler."
}

Write-Host ""
Write-Host "===============================================" -ForegroundColor Green
Write-Host "  OK: Elevation setup complete ($RunAs mode)" -ForegroundColor Green
Write-Host "===============================================" -ForegroundColor Green
Write-Host "  Folder : $dest"
Write-Host "  Server : $Server"
Write-Host "  Standard users can now Install / Update / Uninstall"
Write-Host "  from the dashboard without admin."
