[CmdletBinding()]
param(
    [string]$OutputPath,
    [string]$EnvFile = (Join-Path $PSScriptRoot '..\..\.env.docker'),
    [string]$ComposeFile = (Join-Path $PSScriptRoot '..\..\docker-compose.dev.yml'),
    [string]$ServiceName = 'db',
    [string]$DbName,
    [string]$DbUser,
    [string]$DbPass
)

$ErrorActionPreference = 'Stop'

function Import-GMEnvFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) { return }

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

function Get-GMEnvValue {
    param(
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    $value = [Environment]::GetEnvironmentVariable($Key)
    if ([string]::IsNullOrWhiteSpace($value)) { return $Default }
    return $value.Trim()
}

Import-GMEnvFile -Path $EnvFile

if (-not $DbName) { $DbName = Get-GMEnvValue -Key 'DB_NAME' -Default 'graymentality_landing' }
if (-not $DbUser) { $DbUser = Get-GMEnvValue -Key 'DB_USER' -Default 'graymentality' }
if (-not $DbPass) { $DbPass = Get-GMEnvValue -Key 'DB_PASS' -Default 'graymentality' }

if (-not $OutputPath) {
    $stamp = Get-Date -Format 'yyyy-MM-dd_HH-mm'
    $OutputPath = Join-Path (Join-Path $PSScriptRoot '..\..\backups') ("graymentality_{0}.sql" -f $stamp)
}

$outputDir = Split-Path -Parent $OutputPath
if ($outputDir -and -not (Test-Path -LiteralPath $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is not available on PATH.'
}

$composeDir = Split-Path -Parent $ComposeFile
Push-Location $composeDir
try {
    docker compose --env-file $EnvFile -f $ComposeFile exec -T -e "MYSQL_PWD=$DbPass" $ServiceName mariadb-dump --default-character-set=utf8mb4 --single-transaction --routines --triggers --events --hex-blob -u $DbUser $DbName |
        Set-Content -LiteralPath $OutputPath -Encoding utf8

    if ($LASTEXITCODE -ne 0) {
        throw "Dump failed for database '$DbName'"
    }
}
finally {
    Pop-Location
}

Write-Host "Exported ${ServiceName}:$DbName to $OutputPath"
