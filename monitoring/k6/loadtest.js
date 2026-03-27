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
    chat_predict: {
      executor: "constant-vus",
      vus: 2,
      duration: "2m",
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
