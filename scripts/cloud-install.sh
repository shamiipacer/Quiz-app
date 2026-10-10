#!/usr/bin/env bash
set -euo pipefail
cd /workspace/Quiz-app
export DOCKER_CONFIG=/tmp/quiz-docker
export npm_config_cache=/tmp/quiz-npm-cache
export LOCAL_UID="$(id -u)" LOCAL_GID="$(id -g)"
QUIZ_PROXY_IP="$(getent ahostsv4 proxy 2>/dev/null | awk 'NR==1{print $1}' || true)"
export QUIZ_PROXY_IP
python3 - <<'PY'
import os,secrets,shutil
if not os.path.exists('.env'):
    fd=os.open('.env',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'w') as f:
        f.write('DB_PASSWORD='+secrets.token_hex(24)+'\nDB_ROOT_PASSWORD='+secrets.token_hex(24)+'\n')
if not os.path.exists('backend/.env'):
    shutil.copyfile('backend/.env.example','backend/.env')
    os.chmod('backend/.env',0o600)
PY
build_args=()
if [ -n "$QUIZ_PROXY_IP" ]; then build_args+=(--add-host "proxy:$QUIZ_PROXY_IP"); fi
if [ -f /etc/ssl/certs/ca-certificates.crt ]; then build_args+=(--secret id=system-ca,src=/etc/ssl/certs/ca-certificates.crt); fi
docker build "${build_args[@]}" --build-arg HTTPS_PROXY --build-arg HTTP_PROXY -f backend.Dockerfile -t quiz-php .
docker compose run --rm --no-deps api composer install --prefer-source --no-interaction
if [ -z "${APP_KEY:-}" ] && ! rg -q '^APP_KEY=base64:[^[:space:]]+$' backend/.env; then
    docker compose run --rm --no-deps api php artisan key:generate
fi
docker compose up -d db
docker compose run --rm migrate
if [ ! -L backend/public/storage ]; then docker compose run --rm api php artisan storage:link; fi
npm --prefix mobile ci
docker compose run --rm api composer check-platform-reqs
