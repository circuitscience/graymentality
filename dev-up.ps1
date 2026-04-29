[CmdletBinding()]
param(
    [string]$EnvFile = (Join-Path $PSScriptRoot '.env.local'),
    [string]$ComposeFile = (Join-Path $PSScriptRoot 'compose.yml'),
    [string]$SchemaFile = (Join-Path $PSScriptRoot 'auth_schema.sql')
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

function ConvertTo-MySqlLiteral {
    param([Parameter(Mandatory = $true)][string]$Value)

    return $Value.Replace("'", "''")
}

if (-not (Test-Path -LiteralPath $EnvFile)) {
    throw "Missing env file: $EnvFile"
}

Import-GMEnvFile -Path $EnvFile

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is not available on PATH.'
}

$composeDir = Split-Path -Parent $ComposeFile
Push-Location $composeDir
try {
    Write-Host 'Starting Gray Mentality containers...'
    & docker compose --env-file $EnvFile -f $ComposeFile up -d --build
    if ($LASTEXITCODE -ne 0) { throw 'docker compose up failed.' }

    if (Test-Path -LiteralPath $SchemaFile) {
        $dbName = $env:DB_NAME
        $dbRootPassword = $env:DB_ROOT_PASSWORD
        if ([string]::IsNullOrWhiteSpace($dbName)) {
            throw 'DB_NAME is not set in .env.'
        }
        if ([string]::IsNullOrWhiteSpace($dbRootPassword)) {
            throw 'DB_ROOT_PASSWORD is not set in .env.'
        }

        Write-Host "Ensuring database $dbName exists..."
        & docker compose --env-file $EnvFile -f $ComposeFile exec -T db mariadb -uroot "-p$dbRootPassword" -e "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        if ($LASTEXITCODE -ne 0) { throw 'Failed to create the authentication database.' }

        Write-Host 'Loading auth schema...'
        $schemaSql = Get-Content -LiteralPath $SchemaFile -Raw
        $schemaSql | & docker compose --env-file $EnvFile -f $ComposeFile exec -T db mariadb -uroot "-p$dbRootPassword" $dbName
        if ($LASTEXITCODE -ne 0) { throw 'Failed to load the authentication schema.' }

        Write-Host 'Ensuring app database user has privileges...'
        $dbUserEscaped = ConvertTo-MySqlLiteral -Value $env:DB_USER
        $dbPassEscaped = ConvertTo-MySqlLiteral -Value $env:DB_PASS
        $grantSql = @"
CREATE OR REPLACE USER '$dbUserEscaped'@'%' IDENTIFIED BY '$dbPassEscaped';
CREATE OR REPLACE USER '$dbUserEscaped'@'localhost' IDENTIFIED BY '$dbPassEscaped';
GRANT ALL PRIVILEGES ON $dbName.* TO '$dbUserEscaped'@'%';
GRANT ALL PRIVILEGES ON $dbName.* TO '$dbUserEscaped'@'localhost';
FLUSH PRIVILEGES;
"@
        $grantSql | & docker compose --env-file $EnvFile -f $ComposeFile exec -T db mariadb -uroot "-p$dbRootPassword"
        if ($LASTEXITCODE -ne 0) { throw 'Failed to grant database privileges to the app user.' }
    }
}
finally {
    Pop-Location
}

Write-Host 'Gray Mentality dev stack is up.'
Write-Host 'Preview: http://localhost:8088'
Write-Host 'phpMyAdmin: http://localhost:8090'
