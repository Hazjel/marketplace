import http from "k6/http";
import { check, sleep } from "k6";

// Load test API Laravel (endpoint publik, via nginx)
// Jalankan: docker compose --profile loadtest run --rm --entrypoint "k6 run /scripts/loadtest-api.js" k6
export const options = {
  scenarios: {
    // Browsing: naik bertahap sampai 50 VU
    browse: {
      executor: "ramping-vus",
      startVUs: 0,
      stages: [
        { duration: "30s", target: 20 },
        { duration: "1m", target: 50 },
        { duration: "30s", target: 0 },
      ],
      exec: "browse",
    },
  },
  thresholds: {
    http_req_duration: ["p(95)<2000"], // p95 < 2s
    http_req_failed: ["rate<0.05"],    // error rate < 5%
  },
};

const BASE_URL = "http://nginx:80/api";

export function browse() {
  const endpoints = [
    "/product-category",
    "/product?limit=10",
    "/product/all/paginated?row_per_page=10&page=1",
    "/product/all/paginated?row_per_page=10&search=laptop",
    "/store?limit=10",
    "/store/locations",
    "/health",
  ];
  const path = endpoints[Math.floor(Math.random() * endpoints.length)];
  const res = http.get(`${BASE_URL}${path}`);
  check(res, {
    "status 200": (r) => r.status === 200,
  });
  sleep(Math.random() * 2 + 0.5);
}
