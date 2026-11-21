#!/usr/bin/env bash
# Runs ON THE SERVER. Used by ./deploy.sh (manual, piped over ssh) and by the CI deploy wrapper
# (/opt/lexlink/bin/ci-deploy, ssh forced command). Inputs: BRANCH, FRESH_SEED, NO_BUILD, LOGS, optional DEPLOY_SHA.
BRANCH="${BRANCH:-main}"; FRESH_SEED="${FRESH_SEED:-0}"; NO_BUILD="${NO_BUILD:-0}"; LOGS="${LOGS:-0}"; DEPLOY_SHA="${DEPLOY_SHA:-}"
set -euo pipefail
GEMINI_KEY=""
if [ -f /opt/lexlink/.gemini_key ]; then GEMINI_KEY="$(cat /opt/lexlink/.gemini_key)"; rm -f /opt/lexlink/.gemini_key; fi
ROOT=/opt/lexlink; SRC=$ROOT/src; ENVF=$ROOT/.env
log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

log "git: $BRANCH"
cd "$SRC"
git fetch --quiet origin "$BRANCH"
git checkout --quiet -B "$BRANCH" "origin/$BRANCH"
git reset --quiet --hard "origin/$BRANCH"
if [ -n "$DEPLOY_SHA" ]; then
  # CI deploys exactly the commit that passed the tests
  git cat-file -e "$DEPLOY_SHA^{commit}" 2>/dev/null || { echo "unknown commit $DEPLOY_SHA" >&2; exit 1; }
  git merge-base --is-ancestor "$DEPLOY_SHA" "origin/$BRANCH" || { echo "$DEPLOY_SHA is not on $BRANCH" >&2; exit 1; }
  git reset --quiet --hard "$DEPLOY_SHA"
fi
git log --oneline -1

if [ ! -f "$ENVF" ]; then
  log "creating $ENVF from .env.production.example"
  rnd() { openssl rand -hex "$1"; }
  sed -e "s|__RANDOM_APP_KEY__|base64:$(openssl rand -base64 32)|" \
      -e "s|__RANDOM_DB_PASSWORD__|$(rnd 24)|" \
      -e "s|__RANDOM_DB_ROOT_PASSWORD__|$(rnd 24)|" \
      -e "s|__RANDOM_REVERB_APP_ID__|$((RANDOM * 100 + RANDOM % 100))|" \
      -e "s|__RANDOM_REVERB_APP_KEY__|$(rnd 16)|" \
      -e "s|__RANDOM_REVERB_APP_SECRET__|$(rnd 24)|" \
      .env.production.example > "$ENVF"
fi
# readable by www-data (uid/gid 33) inside the containers, nobody else
chown root:33 "$ENVF"; chmod 640 "$ENVF"
if [ -n "$GEMINI_KEY" ] && grep -qE '^GEMINI_API_KEY=$' "$ENVF"; then
  log "setting GEMINI_API_KEY from local creds"
  K="$GEMINI_KEY" python3 - "$ENVF" <<'PY'
import os, sys, re
p = sys.argv[1]; s = open(p).read()
s = re.sub(r'(?m)^GEMINI_API_KEY=$', 'GEMINI_API_KEY=' + os.environ['K'].replace('\\', '\\\\'), s)
open(p, 'w').write(s)
PY
fi

if [ ! -f "$ROOT/certs/origin.crt" ]; then
  log "generating self-signed origin cert"
  mkdir -p "$ROOT/certs"
  openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
    -keyout "$ROOT/certs/origin.key" -out "$ROOT/certs/origin.crt" \
    -subj "/CN=hackathon.john.uz" \
    -addext "subjectAltName=DNS:hackathon.john.uz" 2>/dev/null
  chmod 644 "$ROOT/certs/origin.crt"; chmod 600 "$ROOT/certs/origin.key"
fi

DC=(docker compose -p lexlink -f "$SRC/docker-compose.prod.yml" --env-file "$ENVF")

if [ "$NO_BUILD" != 1 ]; then
  log "docker build"
  "${DC[@]}" build
fi

log "start mysql + redis"
"${DC[@]}" up -d --wait mysql redis

log "migrate"
"${DC[@]}" run --rm -T --no-deps app php artisan migrate --force </dev/null

if [ "$FRESH_SEED" = 1 ]; then
  log "migrate:fresh --seed (DESTRUCTIVE)"
  # DB::prohibitDestructiveCommands() blocks this when APP_ENV=production, so run the
  # one-off container as APP_ENV=demo (real env vars win over .env in Laravel).
  "${DC[@]}" run --rm -T --no-deps -e APP_ENV=demo app php artisan migrate:fresh --seed --force </dev/null
fi

log "lex.uz knowledge base (offline snapshot)"
"${DC[@]}" run --rm -T --no-deps app php artisan lex:import --from-snapshot </dev/null || true
"${DC[@]}" run --rm -T --no-deps app php artisan lex:embed </dev/null || true

log "up (recreate app services)"
"${DC[@]}" up -d --remove-orphans --force-recreate app horizon reverb scheduler nginx

log "health check"
ok=0
for i in $(seq 1 30); do
  if curl -fs -o /dev/null http://localhost/up 2>/dev/null; then ok=1; break; fi
  sleep 2
done
"${DC[@]}" ps --format 'table {{.Service}}\t{{.Status}}'
docker image prune -f >/dev/null 2>&1 || true
if [ "$ok" != 1 ]; then
  echo "HEALTH CHECK FAILED: http://localhost/up" >&2
  "${DC[@]}" logs --tail=60 app nginx >&2 || true
  exit 1
fi
echo "OK: http://localhost/up -> 200"
if [ "$LOGS" = 1 ]; then "${DC[@]}" logs -f --tail=50; fi
