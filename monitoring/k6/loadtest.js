import http from "k6/http";
import { check, sleep } from "k6";

export const options = {
  vus: 5,
  duration: "1m",
};

export default function () {
  const url = "http://ai-service:8001/predict";
  const payload = JSON.stringify({ message: "apakah ada ayam goreng?" });
  const params = { headers: { "Content-Type": "application/json" } };

  const res = http.post(url, payload, params);
  check(res, { "status is 200": (r) => r.status === 200 });
  sleep(1);
}
