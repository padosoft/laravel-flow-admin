Param(
  [string]$TargetDir = "$(Join-Path $PSScriptRoot '..\..\test-app')"
)

$ErrorActionPreference = 'Stop'

if (Test-Path $TargetDir) {
  Remove-Item -Recurse -Force $TargetDir
}

composer create-project laravel/laravel $TargetDir "^13.0"
Set-Location $TargetDir

composer config repositories.flow-admin path "..\laravel-flow-admin"
composer require padosoft/laravel-flow-admin:@dev
php artisan vendor:publish --tag=flow-admin-config --force
php artisan route:list | Select-String "flow-admin"

Write-Host "Smoke install completed. Open /flow after configuring auth middleware as needed."
