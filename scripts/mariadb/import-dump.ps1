[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$DumpPath,

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

$resolvedDump = (Resolve-Path -LiteralPath $DumpPath -ErrorAction Stop).ProviderPath
if (-not (Test-Path -LiteralPath $resolvedDump)) {
    throw "Dump file not found: $resolvedDump"
}

$containerState = & docker inspect -f '{{.State.Running}}' $ContainerName 2>$null
if ($LASTEXITCODE -ne 0 -or ($containerState | Select-Object -First 1) -notmatch '^true$') {
    throw "Container '$ContainerName' is not running. Start it with: docker compose --env-file .env.local -f docker-compose.dev.yml up -d"
}

function Read-SqlText {
    param([string]$Path)

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

$sql = Read-SqlText -Path $resolvedDump
$sql | docker exec -i -e "MYSQL_PWD=$DbPass" $ContainerName mariadb --default-character-set=utf8mb4 -u $DbUser $DbName

if ($LASTEXITCODE -ne 0) {
    throw "Import failed for database '$DbName'"
}

Write-Host "Imported $resolvedDump into ${ContainerName}:$DbName"