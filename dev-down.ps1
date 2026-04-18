[CmdletBinding()]
param(
    [string]$EnvFile = (Join-Path $PSScriptRoot '.env.docker'),
    [string]$ComposeFile = (Join-Path $PSScriptRoot 'docker-compose.dev.yml')
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is not available on PATH.'
}

$composeDir = Split-Path -Parent $ComposeFile
Push-Location $composeDir
try {
    docker compose --env-file $EnvFile -f $ComposeFile down
}
finally {
    Pop-Location
}

Write-Host 'Gray Mentality dev stack stopped.'
