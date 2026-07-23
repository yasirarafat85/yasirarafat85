<#
    ===============================================================
    App Center - Installer (single, self-contained script)
    ===============================================================
    Windows runs this when an Install/Update/Uninstall button is
    clicked. It reads the app from the server, runs winget (or the
    network installer), and SHOWS A POPUP with the result - so you
    do not have to hunt through logs.

    install-helper.bat writes the server address into $Server below.

    ASCII-only on purpose (safe under any PowerShell encoding).
#>
param([string]$Uri)

$Server = "http://YOUR-SERVER/erp"     # install-helper.bat replaces this line
# tolerate a pasted dashboard URL: keep only up to the erp root
$Server = $Server -replace '/modules/.*$', '' -replace '/index\.php.*$', '' -replace '/+$', ''

$log = Join-Path $env:TEMP 'appdeploy.log'
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m" | Out-File -Append -FilePath $log -Encoding utf8 }
Add-Type -AssemblyName System.Windows.Forms
function Popup($m, $icon) { [System.Windows.Forms.MessageBox]::Show($m, 'App Center', 'OK', $icon) | Out-Null }

$action = 'install'
try {
    Log "URI: $Uri"
    $id     = ([regex]::Match($Uri, 'appdeploy://0*(\d+)')).Groups[1].Value
    $token  = ([regex]::Match($Uri, '[?&]t=([a-fA-F0-9]+)')).Groups[1].Value
    $am     = [regex]::Match($Uri, '[?&]a=(\w+)')
    if ($am.Success) { $action = $am.Groups[1].Value }
    if ([string]::IsNullOrEmpty($id) -or [string]::IsNullOrEmpty($token)) { throw "Bad link format: $Uri" }

    $api = "$Server/modules/appcenter/api.php"
    $url = '{0}?id={1}&t={2}&a={3}&pc={4}' -f $api, $id, $token, $action, $env:COMPUTERNAME
    Log "GET $url"
    $info = Invoke-RestMethod -Uri $url -TimeoutSec 25
    if (-not $info.ok) { throw "Server: $($info.error)" }
    Log "app=$($info.name) type=$($info.type) action=$action"

    if ($info.type -eq 'winget') {
        # extra winget flags from Silent Args (e.g. --architecture x86, --version x, --scope machine)
        $extra = if ([string]::IsNullOrWhiteSpace($info.args)) { '' } else { ' ' + $info.args.Trim() }
        switch ($action) {
            'update'    { $wa = ('upgrade --id "{0}" --silent --accept-package-agreements --accept-source-agreements{1}' -f $info.winget_id, $extra) }
            'uninstall' { $wa = ('uninstall --id "{0}" --silent' -f $info.winget_id) }
            default     { $wa = ('install --id "{0}" --silent --accept-package-agreements --accept-source-agreements{1}' -f $info.winget_id, $extra) }
        }
        Log "winget $wa"
        $of = Join-Path $env:TEMP 'wg_out.txt'
        $ef = Join-Path $env:TEMP 'wg_err.txt'
        $p  = Start-Process winget -ArgumentList $wa -Wait -PassThru -WindowStyle Hidden -RedirectStandardOutput $of -RedirectStandardError $ef
        $txt = ((Get-Content $of, $ef -Raw -ErrorAction SilentlyContinue) -join ' ').Trim()
        Log "winget exit=$($p.ExitCode) output=$txt"
        if ($p.ExitCode -ne 0) { throw "winget failed (code $($p.ExitCode)). $txt" }
    }
    else {
        if ($action -ne 'install') { throw "This is a network app (install only)." }
        if (-not (Test-Path $info.path)) { throw "Installer not found: $($info.path)" }
        if ([string]::IsNullOrWhiteSpace($info.args)) { $p = Start-Process $info.path -Wait -PassThru }
        else { $p = Start-Process $info.path -ArgumentList $info.args -Wait -PassThru }
        Log "installer exit=$($p.ExitCode)"
        if ($p.ExitCode -ne 0) { throw "Installer failed (code $($p.ExitCode))." }
    }

    try { Invoke-RestMethod -Uri $api -Method Post -Body @{ action='report'; id=$id; t=$token; log_id=$info.log_id; status='success' } -TimeoutSec 15 | Out-Null } catch {}
    Log "SUCCESS: $($info.name) ($action)"
    Popup "$($info.name) - $action completed successfully." 'Information'
}
catch {
    $m = $_.Exception.Message
    Log "FAILED ($action): $m"
    Popup "Could not $action.`n`n$m" 'Error'
}
