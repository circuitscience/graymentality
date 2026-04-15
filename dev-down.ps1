[CmdletBinding()]
param(
    [string]$EnvFile = (Join-Path $PSScriptRoot '.env.local'),
    [string]$ComposeFile = (Join-Path $PSScriptRoot 'docker-compose.dev.yml')
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

if (Test-Path -LiteralPath $EnvFile) {
    Import-GMEnvFile -Path $EnvFile
}

if (Get-Command docker -ErrorAction SilentlyContinue) {
    $composeDir = Split-Path -Parent $ComposeFile
    Push-Location $composeDir
    try {
        docker compose --env-file $EnvFile -f $ComposeFile down
    }
    finally {
        Pop-Location
    }
}

Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -match '\\php(\.exe)?$' } | Stop-Process -Force -ErrorAction SilentlyContinue
Write-Host 'Gray Mentality dev stack stopped.'