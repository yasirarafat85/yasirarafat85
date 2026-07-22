<#
    ===============================================================
    App Center - Dispatcher (appdeploy:// protocol handler)
    ===============================================================
    Windows runs this when an Install/Update/Uninstall button is
    clicked. It is lightweight: it writes the request to a queue,
    then decides how to run it elevated (so it works for standard
    users too, without admin):

      1. Is the AppDeployRunner task present?  -> trigger it (SYSTEM/admin)
      2. Otherwise                             -> run the worker here

    The server address comes from config.json next to this file; if
    missing, the $FallbackServer below is used (install-helper.bat
    writes config.json).

    NOTE: keep this file ASCII-only so Windows PowerShell 5.1 parses
    it correctly regardless of file encoding.
#>
param([string]$Uri)

$FallbackServer = "http://YOUR-SERVER/erp"   # setup writes config.json; this is only a fallback

$base  = $PSScriptRoot
$logF  = Join-Path $env:TEMP 'appdeploy.log'
$queue = Join-Path $base 'queue'
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  [disp] $m" | Out-File -Append -FilePath $logF -Encoding utf8 }

try {
    Log "URI: $Uri"

    # appdeploy://<id>?t=<token>&a=<action>
    $m = [regex]::Match($Uri, 'appdeploy://(?<id>\d+)\?t=(?<t>[a-fA-F0-9]+)(&a=(?<a>\w+))?')
    if (-not $m.Success) { throw "Bad URI format." }
    $id     = $m.Groups['id'].Value
    $token  = $m.Groups['t'].Value
    $action = if ($m.Groups['a'].Success) { $m.Groups['a'].Value } else { 'install' }

    # write request into the queue
    New-Item -ItemType Directory -Force -Path $queue | Out-Null
    $reqFile = Join-Path $queue ("{0}.json" -f ([guid]::NewGuid().ToString('N')))
    @{ id = $id; token = $token; action = $action } | ConvertTo-Json -Compress | Set-Content -Path $reqFile -Encoding utf8
    Log "Queued: id=$id action=$action file=$reqFile"

    # ensure config.json exists (worker reads it)
    $cfgPath = Join-Path $base 'config.json'
    if (-not (Test-Path $cfgPath)) {
        @{ server = $FallbackServer.TrimEnd('/') } | ConvertTo-Json | Set-Content -Path $cfgPath -Encoding utf8
    }

    # ---- elevation routing ----
    $null = & schtasks.exe /query /tn "AppDeployRunner" 2>$null
    if ($LASTEXITCODE -eq 0) {
        Log "Runner task found -> trigger (elevated)"
        & schtasks.exe /run /tn "AppDeployRunner" | Out-Null
        Log "task triggered; see worker.log for the install result"
    }
    else {
        $worker = Join-Path $base 'appdeploy-worker.ps1'
        if (-not (Test-Path $worker)) {
            throw "worker script missing: $worker  (copy failed? delete the AppDeploy folder and re-run the installer)"
        }
        Log "No runner task -> run worker directly (current user context)"
        & $worker -ProcessQueue
        Log "worker finished; see worker.log for details"
    }
}
catch {
    Log "DISPATCH ERROR: $($_.Exception.Message)"
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show(
        "Could not send the request.`n`n$($_.Exception.Message)`n`nLog: $logF",
        "App Center", 'OK', 'Error') | Out-Null
}
