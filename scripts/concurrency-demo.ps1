# Concurrency Demo Script - Race Condition Proof
# Usage: powershell -ExecutionPolicy Bypass -File scripts/concurrency-demo.ps1

Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host "  Stock Management Prototype - Concurrency Proof     " -ForegroundColor Cyan
Write-Host "=====================================================" -ForegroundColor Cyan

$baseUrl = "http://127.0.0.1:8000"

Write-Host "Running Concurrency Stress Test via PHP Artisan..." -ForegroundColor Yellow
php artisan test --filter SystemConcurrencyStressTest

Write-Host "`nIntegrity Verification..." -ForegroundColor Yellow
php artisan stock:verify-integrity

Write-Host "`n=====================================================" -ForegroundColor Green
Write-Host "  Concurrency Proof Complete!                        " -ForegroundColor Green
Write-Host "=====================================================" -ForegroundColor Green
