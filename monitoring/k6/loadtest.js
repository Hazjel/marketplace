import http from "k6/http";
import { check, sleep } from "k6";

export const options = {
  scenarios: {
    // Skenario 1: Health check / metrics (ringan, banyak request)
    metrics_check: {
      executor: "constant-vus",
      vus: 10,
      duration: "2m",
      exec: "hitMetrics",
    },
    // Skenario 2: Chat predict (berat, sedikit request karena LLM inference)
    // NOTE: AI service rate-limit 20 req/menit PER IP (lihat ai-service/config.py
    // RATE_LIMIT_PER_MINUTE). k6 mengirim semua VU dari 1 IP container, jadi
    // request/menit skenario ini harus tetap di bawah itu — kalau tidak, hasilnya
    // 429 bukan mengukur performa AI service yang sesungguhnya.
    chat_predict: {
      executor: "constant-arrival-rate",
      rate: 15, // req/menit, di bawah limit 20/menit per IP
      timeUnit: "1m",
      duration: "2m",
      preAllocatedVUs: 2,
      maxVUs: 5,
      exec: "hitPredict",
    },
  },
  thresholds: {
    http_req_duration: ["p(95)<60000"], // p95 < 60s (LLM bisa lambat)
    http_req_failed: ["rate<0.1"],      // error rate < 10%
  },
};

const BASE_URL = "http://ai-service:8001";

const CHAT_MESSAGES = [
  "apakah ada laptop gaming?",
  "harga earphone berapa?",
  "rekomendasi smartphone murah",
  "ada keyboard mechanical?",
  "cari monitor 4k",
];

// Skenario ringan: hit /metrics endpoint
export function hitMetrics() {
  const res = http.get(`${BASE_URL}/metrics`);
  check(res, { "metrics 200": (r) => r.status === 200 });
  sleep(0.5);
}

// Skenario berat: hit /predict endpoint
export function hitPredict() {
  const msg = CHAT_MESSAGES[Math.floor(Math.random() * CHAT_MESSAGES.length)];
  const payload = JSON.stringify({ message: msg });
  const params = { headers: { "Content-Type": "application/json" } };

  const res = http.post(`${BASE_URL}/predict`, payload, params);
  check(res, { "predict 200": (r) => r.status === 200 });
  sleep(1);
}
