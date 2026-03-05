# ============================================
# Blue Marketplace - Full Stack Startup Script
# Run: .\start-all.ps1 dari folder e:\blue
# ============================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  BLUE MARKETPLACE - Starting All...    " -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Redis (Docker)
Write-Host "[1/7] Starting Redis (Docker)..." -ForegroundColor Yellow
docker start redis-blue 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Container not found. Creating redis-blue..." -ForegroundColor DarkYellow
    docker run -d --name redis-blue -p 6379:6379 redis:alpine
}
Write-Host "  Redis OK" -ForegroundColor Green

# 2. Laravel API Server
Write-Host "[2/7] Starting Laravel API Server (port 8000)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd e:\blue\api-blue; php artisan serve" -WindowStyle Normal

# 3. Laravel Queue Worker
Write-Host "[3/7] Starting Queue Worker..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd e:\blue\api-blue; php artisan queue:work" -WindowStyle Minimized

# 4. Laravel Scheduler
Write-Host "[4/7] Starting Scheduler..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd e:\blue\api-blue; php artisan schedule:work" -WindowStyle Minimized

# 5. Laravel Reverb (WebSocket)
Write-Host "[5/7] Starting Reverb WebSocket (port 8080)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd e:\blue\api-blue; php artisan reverb:start" -WindowStyle Minimized

# 6. Vue.js Frontend
Write-Host "[6/7] Starting Vue.js Frontend (port 5173)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd e:\blue\fe-blue; npm run dev" -WindowStyle Normal

# 7. AI Service (FastAPI)
Write-Host "[7/7] Starting AI Service (port 8001)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd e:\blue\ai-service; .\venv\Scripts\activate; uvicorn main:app --reload --port 8001" -WindowStyle Normal

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  ALL SERVICES STARTED SUCCESSFULLY!    " -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "  API Server   : http://localhost:8000"
Write-Host "  Frontend     : http://localhost:5173"
Write-Host "  AI Service   : http://localhost:8001"
Write-Host "  WebSocket    : http://localhost:8080"
Write-Host "  Redis        : localhost:6379"
Write-Host ""
Write-Host "  To stop all: close all PowerShell windows"
Write-Host "  To stop Redis: docker stop redis-blue"
Write-Host ""
