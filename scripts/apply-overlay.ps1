# Copies overlay/api into apps/api, refreshes autoload and runs the whole test suite.
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
Copy-Item overlay/api/* apps/api -Recurse -Force
& .\scripts\dc.ps1 run --rm api composer dump-autoload
if ($LASTEXITCODE -ne 0) { throw "composer dump-autoload failed" }
& .\scripts\dc.ps1 run --rm api php artisan test
if ($LASTEXITCODE -ne 0) { throw "tests failed" }
Write-Host "`nAll tests passed." -ForegroundColor Green
