[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$OutputPath,

    [string]$EnvFile = (Join-Path $PSScriptRoot '..\..\.env.local'),
    [string]$ContainerName = 'graymentality-db',
    [string]$DbName,
    [string]$DbUser,
    [string]$DbPass
)

. (Join-Path $PSScriptRoot 'common.ps1')

Import-GMEnvFile -Path $EnvFile

if (-not $DbName) { $DbName = Get-GMEnvValue -Key 'DB_NAME' -Default 'graymentality_landing' }
if (-not $DbUser) { $DbUser = Get-GMEnvValue -Key 'DB_USER' -Default 'graymentality' }
if (-not $DbPass) { $DbPass = Get-GMEnvValue -Key 'DB_PASS' -Default 'graymentality' }

$targetPath = Split-Path -Parent $OutputPath
if ($targetPath -and -not (Test-Path -LiteralPath $targetPath)) {
    New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
}

$containerState = & docker inspect -f '{{.State.Running}}' $ContainerName 2>$null
if ($LASTEXITCODE -ne 0 -or ($containerState | Select-Object -First 1) -notmatch '^true$') {
    throw "Container '$ContainerName' is not running. Start it with: docker compose --env-file .env.local -f docker-compose.dev.yml up -d"
}

& docker exec -e "MYSQL_PWD=$DbPass" $ContainerName mariadb-dump --default-character-set=utf8mb4 --single-transaction --routines --triggers --events --hex-blob -u $DbUser $DbName |
    Set-Content -LiteralPath $OutputPath -Encoding utf8

if ($LASTEXITCODE -ne 0) {
    throw "Export failed for database '$DbName'"
}

Write-Host "Exported ${ContainerName}:$DbName to $OutputPath"