<#
    ===============================================================
    App Center — Silent Install Helper (Windows)
    ===============================================================
    এই স্ক্রিপ্টটি appdeploy:// প্রোটোকল হ্যান্ডেল করে।
    ড্যাশবোর্ডের "Install" বাটনে ক্লিক করলে Windows এটি চালায়,
    এটি সার্ভার থেকে ইনস্টলারের নেটওয়ার্ক পাথ নিয়ে silent ইনস্টল করে।

    সেটআপ: SETUP.md দেখুন। শুধু নিচের $ServerUrl ঠিক করে দিন।
#>

param([string]$Uri)

# ---- আপনার সার্ভারের ঠিকানা এখানে দিন ----
$ServerUrl = "http://YOUR-SERVER/erp"     # যেমন http://192.168.0.10/erp

# ---- লগ ফাইল (সমস্যা হলে দেখতে) ----
$LogFile = Join-Path $env:TEMP "appdeploy.log"
function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m" | Out-File -Append -FilePath $LogFile -Encoding utf8 }

try {
    Log "Called with URI: $Uri"

    # appdeploy://<id>?t=<token>  থেকে id ও token বের করা
    $match = [regex]::Match($Uri, 'appdeploy://(?<id>\d+)\?t=(?<t>[a-fA-F0-9]+)')
    if (-not $match.Success) { throw "URI ফরম্যাট ঠিক নয়।" }

    $id    = $match.Groups['id'].Value
    $token = $match.Groups['t'].Value

    # সার্ভার থেকে ইনস্টলারের তথ্য নেওয়া
    $api  = "$ServerUrl/modules/appcenter/api.php?id=$id&t=$token"
    Log "Querying API: $api"
    $info = Invoke-RestMethod -Uri $api -TimeoutSec 20

    if (-not $info.ok) { throw "সার্ভার বলছে: $($info.error)" }

    $path = $info.path
    $args = $info.args
    Log "App: $($info.name) | Path: $path | Args: $args"

    if (-not (Test-Path $path)) {
        [System.Windows.Forms.MessageBox]::Show(
            "ইনস্টলার ফাইল পাওয়া যায়নি:`n$path`n`nনেটওয়ার্ক শেয়ারে অ্যাক্সেস আছে কিনা দেখুন।",
            "App Center", 'OK', 'Warning') | Out-Null
        throw "File not found: $path"
    }

    # ইনস্টলার চালানো (silent args থাকলে সেগুলোসহ)
    if ([string]::IsNullOrWhiteSpace($args)) {
        Start-Process -FilePath $path -Wait
    } else {
        Start-Process -FilePath $path -ArgumentList $args -Wait
    }
    Log "Install finished: $($info.name)"
}
catch {
    Log "ERROR: $($_.Exception.Message)"
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show(
        "ইনস্টল করা যায়নি।`n`n$($_.Exception.Message)`n`nবিস্তারিত: $LogFile",
        "App Center", 'OK', 'Error') | Out-Null
}
