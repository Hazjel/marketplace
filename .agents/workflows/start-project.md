---
description: How to start the entire Blue Marketplace project (all services)
---
// turbo-all

# Running Blue Marketplace (Full Stack)

## Prerequisites
- PHP 8.4+, Composer, Node.js 18+, Python 3.10+
- Docker Desktop installed and running
- MySQL running as Windows Service
- All `.env` files configured

## Startup Order

### 1. Start Redis via Docker
```powershell
docker start redis-blue
```
> If container doesn't exist yet, create it first:
> `docker run -d --name redis-blue -p 6379:6379 redis:alpine`

### 2. Start Laravel API Server
```powershell
cd e:\blue\api-blue
php artisan serve
```
> Runs on http://localhost:8000

### 3. Start Laravel Queue Worker
```powershell
cd e:\blue\api-blue
php artisan queue:work
```
> Processes background jobs (image uploads, emails, etc.)

### 4. Start Laravel Scheduler
```powershell
cd e:\blue\api-blue
php artisan schedule:work
```
> Runs scheduled/cron tasks

### 5. Start Laravel Reverb (WebSocket)
```powershell
cd e:\blue\api-blue
php artisan reverb:start
```
> Runs on port 8080 for real-time chat/notifications

### 6. Start Vue.js Frontend
```powershell
cd e:\blue\fe-blue
npm run dev
```
> Runs on http://localhost:5173

### 7. Start AI Service (FastAPI + Gemini)
```powershell
cd e:\blue\ai-service
.\venv\Scripts\activate
uvicorn main:app --reload --port 8001
```
> Runs on http://localhost:8001

## One-Command Startup (Automated)
Instead of opening 6 terminals manually, run:
```powershell
cd e:\blue
.\start-all.ps1
```

## Shutdown
Close all terminal windows, or for Redis:
```powershell
docker stop redis-blue
```
