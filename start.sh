#!/usr/bin/env bash
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "==> Freeing ports 8000 and 5500 if already used"
lsof -ti:8000 | xargs kill -9 2>/dev/null || true
lsof -ti:5500 | xargs kill -9 2>/dev/null || true

cleanup() {
  echo ""
  echo "==> Stopping servers"
  kill $API_PID $WEB_PID 2>/dev/null || true
  lsof -ti:8000 | xargs kill -9 2>/dev/null || true
  lsof -ti:5500 | xargs kill -9 2>/dev/null || true
  wait 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "==> Starting Laravel API on http://localhost:8000"
( cd "$ROOT/backend" && php artisan serve ) &
API_PID=$!

echo "==> Starting frontend on http://localhost:5500"
( cd "$ROOT/frontend" && python3 -m http.server 5500 ) &
WEB_PID=$!

echo ""
echo "Open http://localhost:5500/login.html"
echo "Demo: admin@example.com / password"
echo "Press Ctrl+C to stop."
wait
