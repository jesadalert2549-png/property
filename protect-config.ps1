param(
    [string]$Action = "lock"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Git Protection Manager" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($Action -eq "lock") {
    Write-Host "Locking includes/config.php..." -ForegroundColor Yellow
    git update-index --assume-unchanged includes/config.php
    Write-Host "[OK] includes/config.php is now LOCKED!" -ForegroundColor Green
    Write-Host "Git will ignore local changes to config.php during commit/merge." -ForegroundColor Gray
} elseif ($Action -eq "unlock") {
    Write-Host "Unlocking includes/config.php..." -ForegroundColor Yellow
    git update-index --no-assume-unchanged includes/config.php
    Write-Host "[OK] includes/config.php is UNLOCKED." -ForegroundColor Green
} else {
    $st = git ls-files -v includes/config.php
    if ($st -like "h*") {
        Write-Host "Status: [LOCKED / PROTECTED]" -ForegroundColor Green
    } else {
        Write-Host "Status: [NORMAL / TRACKED]" -ForegroundColor Yellow
    }
}
