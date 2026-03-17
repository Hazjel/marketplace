# Blue Marketplace

Blue Marketplace is a multi-vendor e-commerce platform with a Laravel API, Vue.js frontend, and a local AI assistant ("Ri") powered by Ollama.

## Repository Layout

- `api-blue/` – Laravel API (core backend)
- `fe-blue/` – Vue.js frontend
- `ai-service/` – FastAPI service for the AI assistant
- `monitoring/` – Prometheus, Grafana, and k6 setup
- `docker-compose.yml` – Docker services (Redis, Ollama, AI service, Prometheus, Grafana)

## Services and Ports

- API: `http://localhost:8000`
- Frontend: `http://localhost:5173`
- Reverb (WebSocket): `http://localhost:8080`
- AI Service: `http://localhost:8001`
- Redis: `localhost:6379`
- Ollama (Docker mapped): `http://localhost:11435`
- Prometheus: `http://localhost:9090`
- Grafana: `http://localhost:3000` (admin/admin)

## Prerequisites

- PHP 8.2+ and Composer
- Node.js and npm
- MySQL
- Docker Desktop
- Python 3.10+ (only if you run AI service outside Docker)

## Quick Start (Recommended)

1. Start Docker services (Redis, Ollama, AI, Prometheus, Grafana):
   ```bash
   cd e:\blue
   docker compose up -d --build
   ```
2. Pull the Ollama model in the container (first time only):
   ```bash
   docker compose exec -T ollama ollama pull qwen3:8b
   ```
3. Start Laravel API:
   ```bash
   cd e:\blue\api-blue
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate:fresh --seed
   php artisan serve
   ```
4. Start frontend:
   ```bash
   cd e:\blue\fe-blue
   npm install
   npm run dev
   ```

## AI Service (Ollama Local)

The AI assistant uses the local Ollama model `qwen3:8b`. The AI service is containerized by default.

Environment variables (Docker or local):
```
OLLAMA_BASE_URL=http://localhost:11435
OLLAMA_MODEL=qwen3:8b
OLLAMA_TIMEOUT_S=180
DB_HOST=127.0.0.1
DB_USER=root
DB_PASSWORD=
DB_NAME=api_blue
```

If you want to run the AI service outside Docker:
```bash
cd e:\blue\ai-service
python -m venv venv
.\venv\Scripts\activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8001
```

## Monitoring (Prometheus + Grafana)

- Metrics endpoint: `http://localhost:8001/metrics`
- Prometheus targets: `http://localhost:9090/targets`
- Grafana: `http://localhost:3000` (admin/admin)

Grafana dashboard is auto-provisioned:
1. Open Grafana
2. Go to **Dashboards → Browse**
3. Select **AI Service Monitoring**

## Load Testing (k6)

Run the built-in load test:
```bash
cd e:\blue
docker compose --profile loadtest run --rm k6
```

## Notes

- Docker Compose runs: Redis, Ollama, AI service, Prometheus, Grafana.
- Laravel API and Vue frontend are started separately (see Quick Start).
- If you have a local helper script `start-all.ps1`, you can use it to start services in one go.

## Troubleshooting

- If Grafana is not reachable, restart it:
  ```bash
  docker compose up -d grafana
  ```
- If the model is not found, pull it inside the Ollama container:
  ```bash
  docker compose exec -T ollama ollama pull qwen3:8b
  ```
