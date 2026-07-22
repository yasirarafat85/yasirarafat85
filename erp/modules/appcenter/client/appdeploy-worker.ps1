<#
    ===============================================================
    App Center - Worker (does the actual work)
    ===============================================================
    Runs in an elevated context (SYSTEM Scheduled Task or admin
    account), so it can install/update/uninstall on standard-user
    PCs without admin.

    Job: read requests from the queue folder -> ask the server API
         for details -> run winget/installer -> report the result.

    This version logs verbosely (each step, the exact command, and
    winget's real stdout/stderr) so worker.log tells you exactly why
    something did or did not install.

    NOTE: keep this file ASCII-only so Windows PowerShell 5.1 parses
    it correctly regardless of file encoding.
#>
param(
    [switch]$ProcessQueue,
    [string]$RequestFile
)

$base  = $PSScriptRoot
$logF  = Join-Path $base 'worker.log'
$queue = Join-Path $base 'queue'
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  [worker] $m" | Out-File -Append -FilePath $logF -Encoding utf8 }

Log "======== worker start (user=$env:USERNAME, pc=$env:COMPUTERNAME) ========"

# server address from config.json
try {
    $cfgPath = Join-Path $base 'config.json'
    if (-not (Test-Path $cfgPath)) { Log "FATAL: config.json not found at $cfgPath"; return }
    $cfg    = Get-Content $cfgPath -Raw | ConvertFrom-Json
    $server = $cfg.server.TrimEnd('/')
    Log "config server = $server"
} catch {
    Log "FATAL: cannot read config.json: $($_.Exception.Message)"; return
}
$api = "$server/modules/appcenter/api.php"

# find winget.exe (may not be on PATH, esp. under SYSTEM)
function Find-Winget {
    $c = Get-Command winget.exe -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $p = Get-ChildItem "$env:ProgramFiles\WindowsApps" -Filter winget.exe -Recurse -ErrorAction SilentlyContinue |
         Sort-Object FullName -Descending | Select-Object -First 1
    if ($p) { return $p.FullName }
    return $null
}

function Invoke-Request($file) {
    $id=''; $token=''; $action='install'; $logId=0
    Log "---- request file: $file ----"
    try {
        $raw = Get-Content $file -Raw
        Log "request json: $raw"
        $r      = $raw | ConvertFrom-Json
        $id     = $r.id; $token = $r.token
        $action = if ($r.action) { $r.action } else { 'install' }
        $pc     = $env:COMPUTERNAME

        $url = "$api?id=$id&t=$token&a=$action&pc=$pc"
        Log "calling API: $url"
        try {
            $info = Invoke-RestMethod -Uri $url -TimeoutSec 20
        } catch {
            throw "cannot reach server API ($($_.Exception.Message)). Check server URL in config.json and network."
        }
        if (-not $info.ok) { throw "server refused: $($info.error)" }
        $logId = $info.log_id
        Log "server ok: name=$($info.name) type=$($info.type) log_id=$logId args=$($info.args)"

        if ($info.type -eq 'winget') {
            $wingetExe = Find-Winget
            if (-not $wingetExe) {
                throw "winget not found on this PC. Install 'App Installer' from Microsoft Store. (Note: SYSTEM context may not see per-user winget.)"
            }
            Log "winget exe: $wingetExe"
            Log "winget id: $($info.winget_id)"

            $common = "--silent --accept-package-agreements --accept-source-agreements"
            switch ($action) {
                'update'    { $wa = "upgrade   --id `"$($info.winget_id)`" $common" }
                'uninstall' { $wa = "uninstall --id `"$($info.winget_id)`" --silent" }
                default     { $wa = "install   --id `"$($info.winget_id)`" $common" }
            }
            Log "RUN: winget $wa"

            # capture winget's real output so we can see WHY it failed
            $outF = Join-Path $env:TEMP ("wg_out_{0}.txt" -f ([guid]::NewGuid().ToString('N')))
            $errF = Join-Path $env:TEMP ("wg_err_{0}.txt" -f ([guid]::NewGuid().ToString('N')))
            $p = Start-Process -FilePath $wingetExe -ArgumentList $wa -Wait -PassThru -WindowStyle Hidden `
                    -RedirectStandardOutput $outF -RedirectStandardError $errF
            $out = (Get-Content $outF -Raw -ErrorAction SilentlyContinue)
            $er  = (Get-Content $errF -Raw -ErrorAction SilentlyContinue)
            Remove-Item $outF, $errF -Force -ErrorAction SilentlyContinue

            Log "winget exit code: $($p.ExitCode)"
            if ($out) { Log "winget stdout:`n$out" }
            if ($er)  { Log "winget stderr:`n$er" }

            if ($p.ExitCode -ne 0) {
                $snippet = (($er + ' ' + $out).Trim() -replace '\s+', ' ')
                if ($snippet.Length -gt 200) { $snippet = $snippet.Substring(0, 200) }
                throw "winget exit $($p.ExitCode): $snippet"
            }
        }
        else {
            if ($action -ne 'install') { throw "action '$action' not allowed for a network app." }
            Log "installer path: $($info.path)"
            if (-not (Test-Path $info.path)) { throw "installer not found (no access?): $($info.path)" }
            Log "RUN: $($info.path) $($info.args)"
            if ([string]::IsNullOrWhiteSpace($info.args)) {
                $p = Start-Process -FilePath $info.path -Wait -PassThru
            } else {
                $p = Start-Process -FilePath $info.path -ArgumentList $info.args -Wait -PassThru
            }
            Log "installer exit code: $($p.ExitCode)"
            if ($p.ExitCode -ne 0) { throw "installer exit code: $($p.ExitCode)" }
        }

        Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$logId; status='success' } -TimeoutSec 15 | Out-Null
        Log "RESULT: SUCCESS - $($info.name)"
    }
    catch {
        $msg = $_.Exception.Message
        Log "RESULT: FAILED - $msg"
        try {
            if ($logId -gt 0) {
                Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$logId; status='failed'; note=$msg } -TimeoutSec 15 | Out-Null
                Log "reported failure to server (log_id=$logId)"
            } else {
                Log "no log_id - failure not reported to server (never reached API)."
            }
        } catch { Log "could not report failure: $($_.Exception.Message)" }
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
        $files = Get-ChildItem $queue -Filter *.json -ErrorAction SilentlyContinue | Sort-Object CreationTime
        Log "queue has $($files.Count) request(s)"
        $files | ForEach-Object { Invoke-Request $_.FullName }
    } else {
        Log "queue folder not found: $queue"
    }
}
Log "======== worker end ========"
