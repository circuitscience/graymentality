[CmdletBinding()]
param(
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8088,
    [string]$EnvFile = (Join-Path $PSScriptRoot '.env')
)

$ErrorActionPreference = 'Stop'

function Import-GMEnvFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    foreach ($rawLine in Get-Content -LiteralPath $Path) {
        $line = ($rawLine ?? '').Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { continue }
        if ($line -notmatch '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$') { continue }

        $key = $matches[1].Trim()
        $value = $matches[2].Trim()

        if ((($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) -and $value.Length -ge 2) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        Set-Item -Path ("Env:{0}" -f $key) -Value $value
    }
}

Import-GMEnvFile -Path $EnvFile

$php = Get-Command php -ErrorAction Stop
$publicRoot = Join-Path $PSScriptRoot 'public'
$routerScript = Join-Path $publicRoot 'router.php'
if (-not (Test-Path -LiteralPath $publicRoot)) {
    throw "Missing public directory: $publicRoot"
}

Write-Host "Serving Gray Mentality from $publicRoot"
Write-Host "URL: http://$ListenHost`:$Port"
Write-Host "Using env file: $EnvFile"
Write-Host "Stop with Ctrl+C"

& $php.Source -S "$ListenHost`:$Port" -t $publicRoot $routerScript
