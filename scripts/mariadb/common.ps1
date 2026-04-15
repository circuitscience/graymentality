function Import-GMEnvFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    foreach ($rawLine in Get-Content -LiteralPath $Path) {
        $line = ($rawLine ?? '').Trim()
        if ($line -eq '' -or $line.StartsWith('#')) {
            continue
        }

        if ($line -notmatch '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$') {
            continue
        }

        $key = $matches[1].Trim()
        $value = $matches[2].Trim()

        if (
            ($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))
        ) {
            if ($value.Length -ge 2) {
                $value = $value.Substring(1, $value.Length - 2)
            }
        }

        Set-Item -Path ("Env:{0}" -f $key) -Value $value
    }
}

function Get-GMEnvValue {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Key,
        [string]$Default = ''
    )

    $value = [Environment]::GetEnvironmentVariable($Key)
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $Default
    }

    return $value.Trim()
}