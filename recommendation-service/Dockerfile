FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

WORKDIR /app

COPY requirements.txt ./
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
    && pip install --no-cache-dir -r requirements.txt \
    && apt-get purge -y --auto-remove build-essential \
    && rm -rf /var/lib/apt/lists/*

COPY . .

EXPOSE 8002

# "sh script.sh" (bukan "./script.sh") -- bind-mount ./recommendation-service:/app
# nimpa docker-entrypoint.sh dari image (yang udah di-chmod +x) dengan versi
# host, yang di Windows/git gak selalu bawa execute bit. Invoke eksplisit
# lewat sh gak butuh execute bit sama sekali.
ENTRYPOINT ["sh", "docker-entrypoint.sh"]
