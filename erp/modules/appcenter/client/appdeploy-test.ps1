<#
    ===============================================================
    App Center - Diagnostic / Health Check
    ===============================================================
    Run this on a client PC to see, at a glance, why installs may
    not be working. It checks the helper files, config, server
    reachability, winget, the protocol, and the runner task, then
    prints a clear PASS/FAIL report and the last log lines.

    Usage (PowerShell):
        .\appdeploy-test.ps1
        .\appdeploy-test.ps1 -TestWinget      # also try a real winget install (7-Zip)

    NOTE: ASCII-only on purpose (safe under any encoding).
#>
param([switch]$TestWinget)

function Line($s) { Write-Host $s }
function Ok($s)   { Write-Host "  [OK]   $s" -ForegroundColor Green }
function Bad($s)  { Write-Host "  [FAIL] $s" -ForegroundColor Red }
function Warn($s) { Write-Host "  [WARN] $s" -ForegroundColor Yellow }

Line ""
Line "=============================================="
Line "   App Center - Diagnostic"
Line "   PC=$env:COMPUTERNAME  User=$env:USERNAME"
Line "=============================================="

# --- 1. locate helper folder (simple mode or elevation mode) ---
$candidates = @("$env:LOCALAPPDATA\AppDeploy", "$env:ProgramData\AppDeploy")
$base = $null
foreach ($c in $candidates) { if (Test-Path (Join-Path $c 'appdeploy.ps1')) { $base = $c; break } }

Line ""
Line "1) Helper files"
if (-not $base) {
    Bad "AppDeploy folder / appdeploy.ps1 not found in either:"
    $candidates | ForEach-Object { Line "        $_" }
    Bad "=> Helper not installed. Run install-helper.bat (or setup-elevation.ps1)."
    return
}
Ok "folder: $base"
foreach ($f in 'appdeploy.ps1','appdeploy-worker.ps1','config.json') {
    if (Test-Path (Join-Path $base $f)) { Ok "found $f" } else { Bad "MISSING $f (copy failed?) - delete folder and re-run installer" }
}

# --- 2. config / server ---
Line ""
Line "2) Config (server URL)"
$server = $null
try {
    $server = ((Get-Content (Join-Path $base 'config.json') -Raw | ConvertFrom-Json).server).TrimEnd('/')
    Ok "server = $server"
} catch { Bad "config.json unreadable: $($_.Exception.Message)" }

# --- 3. server reachable ---
Line ""
Line "3) Server reachable"
if ($server) {
    try {
        $r = Invoke-WebRequest -Uri "$server/auth/login.php" -UseBasicParsing -TimeoutSec 10
        if ($r.StatusCode -eq 200) { Ok "login page reachable ($server)" } else { Warn "unexpected status $($r.StatusCode)" }
    } catch {
        Bad "cannot reach $server ($($_.Exception.Message))"
        Warn "Fix: use the server IP (e.g. http://192.168.0.10/erp); check firewall/hostname."
    }
}

# --- 4. winget ---
Line ""
Line "4) winget (Windows Package Manager)"
$wg = Get-Command winget.exe -ErrorAction SilentlyContinue
if (-not $wg) {
    $p = Get-ChildItem "$env:ProgramFiles\WindowsApps" -Filter winget.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($p) { $wg = $p; Warn "winget not on PATH but found: $($p.FullName)" }
}
if ($wg) {
    $wgPath = if ($wg.Source) { $wg.Source } else { $wg.FullName }
    Ok "winget path: $wgPath"
    try { $v = & $wgPath --version 2>$null; Ok "winget version: $v" } catch { Warn "winget found but --version failed" }
} else {
    Bad "winget NOT found. Install 'App Installer' from Microsoft Store."
    Warn "winget apps will fail until this is fixed. (network apps still work via Download.)"
}

# --- 5. protocol registered ---
Line ""
Line "5) appdeploy:// protocol"
$hkcu = Test-Path 'HKCU:\Software\Classes\appdeploy\shell\open\command'
$hklm = Test-Path 'HKLM:\SOFTWARE\Classes\appdeploy\shell\open\command'
if ($hkcu) { Ok "registered for current user (HKCU)" }
if ($hklm) { Ok "registered machine-wide (HKLM)" }
if (-not $hkcu -and -not $hklm) { Bad "protocol NOT registered - Install button will do nothing. Re-run installer." }

# --- 6. runner task (standard-user elevation mode) ---
Line ""
Line "6) AppDeployRunner task (for standard users)"
$null = & schtasks.exe /query /tn "AppDeployRunner" 2>$null
if ($LASTEXITCODE -eq 0) { Ok "task present (elevation mode) - standard users can install" }
else { Warn "no task (simple mode). OK if this PC's user is admin; standard users need setup-elevation.ps1." }

# --- 7. recent logs ---
Line ""
Line "7) Recent worker.log (last 15 lines)"
$wl = Join-Path $base 'worker.log'
if (Test-Path $wl) { Get-Content $wl -Tail 15 | ForEach-Object { Line "     $_" } }
else { Warn "no worker.log yet (worker never ran) - click Install once, then re-run this test." }

Line ""
Line "   Dispatcher log: $env:TEMP\appdeploy.log (last 8 lines)"
$dl = Join-Path $env:TEMP 'appdeploy.log'
if (Test-Path $dl) { Get-Content $dl -Tail 8 | ForEach-Object { Line "     $_" } }

# --- 8. optional real winget test ---
if ($TestWinget -and $wg) {
    Line ""
    Line "8) Live winget test: installing 7-Zip (output below)"
    $wgPath = if ($wg.Source) { $wg.Source } else { $wg.FullName }
    & $wgPath install --id 7zip.7zip --silent --accept-package-agreements --accept-source-agreements
    Line "   winget exit code: $LASTEXITCODE  (0 = success)"
}

Line ""
Line "=============================================="
Line "   Done. Send the FAIL/WARN lines + section 7"
Line "   (worker.log) to get the exact fix."
Line "=============================================="
Line ""
