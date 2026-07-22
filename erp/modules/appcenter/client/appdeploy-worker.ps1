<#
    ===============================================================
    App Center - Worker (does the actual work)
    ===============================================================
    Runs in an elevated context (SYSTEM Scheduled Task or admin
    account), so it can install/update/uninstall on standard-user
    PCs without admin - the same idea as the Windows Update SYSTEM
    service.

    Job: read requests from the queue folder -> ask the server API
         for details -> run winget/installer -> report the result.

    Do not run directly - appdeploy.ps1 (dispatcher) or the
    Scheduled Task invokes it. Config comes from config.json.

    NOTE: keep this file ASCII-only so Windows PowerShell 5.1 parses
    it correctly regardless of file encoding.
#>
param(
    [switch]$ProcessQueue,        # process all requests in the queue
    [string]$RequestFile          # or a single request file
)

$base   = $PSScriptRoot
$logF   = Join-Path $base 'worker.log'
$queue  = Join-Path $base 'queue'
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  [worker] $m" | Out-File -Append -FilePath $logF -Encoding utf8 }

# server address from config.json
try {
    $cfg    = Get-Content (Join-Path $base 'config.json') -Raw | ConvertFrom-Json
    $server = $cfg.server.TrimEnd('/')
} catch {
    Log "cannot read config.json: $($_.Exception.Message)"; return
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
        if (-not $info.ok) { throw "server says: $($info.error)" }
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
            if ($action -ne 'install') { throw "action '$action' not allowed for a network app." }
            if (-not (Test-Path $info.path)) { throw "installer not found: $($info.path)" }
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
