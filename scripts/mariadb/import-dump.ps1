[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$DumpPath,
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

function Read-SqlText {
    param([Parameter(Mandatory = $true)][string]$Path)

    if ($Path.ToLowerInvariant().EndsWith('.gz')) {
        $fs = [System.IO.File]::OpenRead($Path)
        try {
            $gzip = [System.IO.Compression.GzipStream]::new($fs, [System.IO.Compression.CompressionMode]::Decompress)
            try {
                $reader = [System.IO.StreamReader]::new($gzip, [System.Text.Encoding]::UTF8)
                try {
                    return $reader.ReadToEnd()
                }
                finally {
                    $reader.Dispose()
                }
            }
            finally {
                $gzip.Dispose()
            }
        }
        finally {
            $fs.Dispose()
        }
    }

    return Get-Content -LiteralPath $Path -Raw -Encoding utf8
}

Import-GMEnvFile -Path $EnvFile

if (-not $DbName) { $DbName = Get-GMEnvValue -Key 'DB_NAME' -Default 'jerrybil_graymentality' }
if (-not $DbUser) { $DbUser = Get-GMEnvValue -Key 'DB_USER' -Default '' }
if (-not $DbPass) { $DbPass = Get-GMEnvValue -Key 'DB_PASS' -Default '' }

$resolvedDump = (Resolve-Path -LiteralPath $DumpPath -ErrorAction Stop).ProviderPath
if (-not (Test-Path -LiteralPath $resolvedDump)) {
    throw "Dump file not found: $resolvedDump"
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is not available on PATH.'
}

$sql = Read-SqlText -Path $resolvedDump
$composeDir = Split-Path -Parent $ComposeFile
Push-Location $composeDir
try {
    $sql | docker compose --env-file $EnvFile -f $ComposeFile exec -T -e "MYSQL_PWD=$DbPass" $ServiceName mariadb --default-character-set=utf8mb4 -u $DbUser $DbName
    if ($LASTEXITCODE -ne 0) {
        throw "Import failed for database '$DbName'"
    }
}
finally {
    Pop-Location
}

Write-Host "Imported $resolvedDump into ${ServiceName}:$DbName"
