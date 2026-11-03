$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$phpDirectory = Join-Path $projectRoot '.runtime/php84'
if (-not (Test-Path (Join-Path $phpDirectory 'php.exe'))) {
    throw 'Install official PHP 8.4 locally in .runtime/php84 or use a PHP 8.4 system runtime.'
}
$env:PATH = "$phpDirectory;$env:PATH"
$env:COMPOSER_CACHE_DIR = Join-Path $projectRoot '.composer-cache'
Write-Output 'Project PHP 8.4 selected for this PowerShell session only.'
