#!/usr/bin/env bash
set -euo pipefail
cd /workspace/Quiz-app
export DOCKER_CONFIG=/tmp/quiz-docker
export LOCAL_UID="$(id -u)" LOCAL_GID="$(id -g)"
export QUIZ_PROXY_IP="$(getent ahostsv4 proxy 2>/dev/null | awk 'NR==1{print $1}' || true)"
docker compose up -d db
docker compose run --rm api php artisan optimize:clear
docker compose up -d api scheduler worker
python3 - <<'PY'
import time,urllib.request
for attempt in range(30):
    try:
        with urllib.request.urlopen('http://127.0.0.1:8000/up',timeout=3) as r:
            if r.status==200:break
    except OSError: pass
    if attempt==29:raise SystemExit('API did not become healthy; inspect container logs without dumping credentials.')
    time.sleep(0.5)
print('API health check passed.')
PY
docker compose exec -T api php artisan migrate:status
