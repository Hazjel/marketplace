# Blue Marketplace

Multi-vendor e-commerce platform untuk gadget dan elektronik, dibangun dengan **Laravel 12**, **Vue 3**, **FastAPI AI chatbot**, dan **Flutter mobile app**.

## Arsitektur

```
api-blue/       → Laravel 12 REST API (PHP 8.2+, Sanctum, Spatie Permission)
fe-blue/        → Vue 3 SPA (Vite, Tailwind v4, Pinia, Radix Vue)
ai-service/     → FastAPI chatbot "Ri" (Ollama, qwen3:1.7b)
mobile_blue/    → Flutter mobile app
monitoring/     → Prometheus + Grafana + k6 load test
postman/        → Postman collection (94 endpoint)
docker-compose  → Redis, Ollama, AI Service, Prometheus, Grafana
```

## Tech Stack

| Layer | Stack |
|-------|-------|
| Backend | Laravel 12, MySQL, MongoDB (product variants), Redis, Sanctum, Spatie Permission |
| Frontend | Vue 3 Composition API, Vite 7, Tailwind CSS v4, Pinia, Laravel Echo |
| AI Service | FastAPI, Ollama (`qwen3:1.7b`), Prometheus metrics |
| Mobile | Flutter |
| Monitoring | Prometheus, Grafana, k6 |
| Payment | Midtrans |
| Real-time | Laravel Reverb (WebSocket) |

## Ports

| Service | URL |
|---------|-----|
| Laravel API | http://localhost:8000 |
| Vue Frontend | http://localhost:5173 |
| Reverb WS | http://localhost:8080 |
| AI Service | http://localhost:8001 |
| Redis | localhost:6379 |
| Ollama | http://localhost:11435 |
| Prometheus | http://localhost:9090 |
| Grafana | http://localhost:3000 |

## Prerequisites

- PHP 8.2+, Composer
- Node.js 18+, npm
- MySQL
- Docker Desktop
- Python 3.10+ (opsional, jika AI service dijalankan di luar Docker)

## Quick Start

### 1. Docker Services

```bash
docker compose up -d --build
docker compose exec -T ollama ollama pull qwen3:1.7b   # pertama kali saja
```

### 2. Laravel API

```bash
cd api-blue
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
php artisan queue:work        # terminal terpisah
php artisan reverb:start      # terminal terpisah
```

### 3. Vue Frontend

```bash
cd fe-blue
npm install
npm run dev
```

### 4. AI Service (opsional, jika di luar Docker)

```bash
cd ai-service
python -m venv venv
venv/Scripts/activate         # Windows
pip install -r requirements.txt
uvicorn main:app --reload --port 8001
```

## Monitoring

AI Service mengekspos metrics Prometheus di `/metrics`. Stack monitoring berjalan otomatis via Docker Compose.

- **Prometheus**: http://localhost:9090/targets — cek scrape status
- **Grafana**: http://localhost:3000 (admin/admin) — dashboard auto-provisioned di **Dashboards → AI Service Monitoring**

### Load Testing

```bash
docker compose --profile loadtest run --rm k6
```

Menjalankan 2 skenario: health check (10 VU) dan chat predict (2 VU) selama 2 menit.

## Troubleshooting

| Problem | Solusi |
|---------|--------|
| Grafana tidak tampil | `docker compose restart grafana` |
| Model Ollama not found | `docker compose exec -T ollama ollama pull qwen3:1.7b` |
| AI service error koneksi Ollama | Pastikan container Ollama running: `docker compose ps` |
| 401 redirect loop di frontend | Clear cookie `token` di browser |
