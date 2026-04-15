[CmdletBinding()]
param(
    [string]$EnvFile = (Join-Path $PSScriptRoot '.env.local'),
    [string]$ComposeFile = (Join-Path $PSScriptRoot 'docker-compose.dev.yml'),
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8088
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

if (-not (Test-Path -LiteralPath $EnvFile)) {
    throw "Missing env file: $EnvFile"
}

Import-GMEnvFile -Path $EnvFile

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is not available on PATH.'
}

if (-not (Get-Command pwsh -ErrorAction SilentlyContinue)) {
    throw 'pwsh is not available on PATH.'
}

$composeDir = Split-Path -Parent $ComposeFile
Push-Location $composeDir
try {
    Write-Host 'Starting MariaDB and phpMyAdmin...'
    & docker compose --env-file $EnvFile -f $ComposeFile up -d
    if ($LASTEXITCODE -ne 0) {
        throw 'docker compose up failed.'
    }
}
finally {
    Pop-Location
}

$serverScript = Join-Path $PSScriptRoot 'serve-local.ps1'
$serverArgs = @('-NoProfile', '-File', $serverScript, '-ListenHost', $ListenHost, '-Port', $Port, '-EnvFile', $EnvFile)

Write-Host "Starting PHP preview server on http://$ListenHost`:$Port ..."
Start-Process -FilePath 'pwsh' -ArgumentList $serverArgs | Out-Null
Write-Host 'Gray Mentality dev stack is up.'
Write-Host "Preview: http://$ListenHost`:$Port"
Write-Host 'phpMyAdmin: http://127.0.0.1:8090'
