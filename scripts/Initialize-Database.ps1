param([string]$PgBin = 'C:\Program Files\PostgreSQL\16\bin')
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$data = Join-Path $PWD '.runtime/postgres'
$port = 55432
if (Test-Path (Join-Path $data 'PG_VERSION')) {
    throw 'Project cluster already exists. Use pg_ctl to start it; this script never resets a cluster.'
}
New-Item -ItemType Directory -Force .runtime | Out-Null
$password = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(36))
$pwFile = Join-Path $PWD '.runtime/db-password'
Set-Content -LiteralPath $pwFile -Value $password -NoNewline
& "$PgBin/initdb.exe" -D $data -U cyberaware_owner --auth=scram-sha-256 --encoding=UTF8 --locale=C "--pwfile=$pwFile"
if ($LASTEXITCODE -ne 0) { throw 'initdb failed' }
Add-Content (Join-Path $data 'postgresql.conf') "`nlisten_addresses = '127.0.0.1'`nport = $port`n"
& "$PgBin/pg_ctl.exe" -D $data -l "$PWD/.runtime/postgres.log" -w start
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL start failed' }
$env:PGPASSWORD = $password
try {
    & "$PgBin/createdb.exe" -h 127.0.0.1 -p $port -U cyberaware_owner cyberaware_ai_dev
    if ($LASTEXITCODE -ne 0) { throw 'Database creation failed' }
} finally { Remove-Item Env:PGPASSWORD }
$content = Get-Content .env -Raw
foreach ($entry in @{
    DB_CONNECTION='pgsql'; DB_HOST='127.0.0.1'; DB_PORT="$port";
    DB_DATABASE='cyberaware_ai_dev'; DB_USERNAME='cyberaware_owner'; DB_PASSWORD=$password
}.GetEnumerator()) {
    $content = [regex]::Replace($content, '(?m)^#?\s*'+$entry.Key+'=.*$', $entry.Key+'='+$entry.Value)
}
Set-Content .env $content
Write-Output 'Dedicated development database created on 127.0.0.1:55432. Credentials are local and ignored by Git.'
