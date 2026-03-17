Before installing Python deps, please follow these steps.

## Local Dev (Ollama)

1. `cd ai-service`
2. `python -m venv venv`
3. `.\venv\Scripts\activate` (windows) / `source venv/bin/activate` (mac/linux)
4. `pip install -r requirements.txt`
5. Make sure Ollama is running and pull the model:
   - `ollama pull qwen3:8b`
6. Run API:
   - `uvicorn main:app --reload --port 8001`

## Docker Compose (Ollama + API)

1. Pastikan model sudah terinstall di container Ollama:
   - `docker compose run --rm ollama ollama pull qwen3:8b`
2. Jalankan:
   - `docker compose up --build`

## Troubleshooting

- Cek Ollama berjalan:
  - `ollama list`
- Cek model tersedia via HTTP:
  - Local Ollama: `curl http://localhost:11434/api/tags`
  - Docker Ollama (mapped): `curl http://localhost:11435/api/tags`
