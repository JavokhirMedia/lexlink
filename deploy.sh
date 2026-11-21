#!/usr/bin/env bash
# LexLink one-command deploy (run locally from the repo root).
#
#   ./deploy.sh                 pull origin/main on the server, build, migrate, restart, health-check
#   ./deploy.sh --fresh-seed    ... and then migrate:fresh --seed  (DESTRUCTIVE: wipes the DB, demo only)
#   ./deploy.sh --no-build      skip docker build (e.g. only .env changed)
#   ./deploy.sh --logs          tail logs of all services after deploy
#
# Server settings are NOT stored in git: put them in .deploy.env (gitignored) next to this script:
#   LEXLINK_HOST=user@host   LEXLINK_SSH_KEY=~/.ssh/your_key   (optional) LEXLINK_BRANCH=main
# LEXLINK_CREDS (../creds.txt) — optional GEMINI_API_KEY= source.
# Normal deploys run from GitHub Actions on merge to main (.github/workflows/ci-cd.yml);
# this script is for manual operations such as --fresh-seed.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
[ -f "$SCRIPT_DIR/.deploy.env" ] && . "$SCRIPT_DIR/.deploy.env"
HOST="${LEXLINK_HOST:?set LEXLINK_HOST in .deploy.env}"
KEY="${LEXLINK_SSH_KEY:?set LEXLINK_SSH_KEY in .deploy.env}"
KEY="${KEY/#\~/$HOME}"
BRANCH="${LEXLINK_BRANCH:-main}"
CREDS="${LEXLINK_CREDS:-$SCRIPT_DIR/../creds.txt}"

FRESH_SEED=0; NO_BUILD=0; LOGS=0
for arg in "$@"; do
  case "$arg" in
    --fresh-seed) FRESH_SEED=1 ;;
    --no-build)   NO_BUILD=1 ;;
    --logs)       LOGS=1 ;;
    -h|--help)    sed -n '2,12p' "$0"; exit 0 ;;
    *) echo "unknown option: $arg" >&2; exit 2 ;;
  esac
done

SSH=(ssh -i "$KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new "$HOST")

if [ "$FRESH_SEED" = 1 ]; then
  echo "!! --fresh-seed will DROP ALL TABLES on the production DB and reseed demo data."
  if [ "${LEXLINK_YES:-0}" != 1 ]; then
    read -r -p "Type 'wipe' to continue: " ans
    [ "$ans" = "wipe" ] || { echo "aborted"; exit 1; }
  fi
fi

# Optional Gemini key from local creds file — sent over ssh stdin, never printed.
GEMINI_KEY=""
if [ -f "$CREDS" ]; then
  GEMINI_KEY="$(grep -E '^GEMINI_API_KEY=' "$CREDS" | head -n1 | cut -d= -f2- | tr -d '"'"'"'\r' || true)"
fi

if [ -n "$GEMINI_KEY" ]; then
  printf '%s' "$GEMINI_KEY" | "${SSH[@]}" 'umask 077; mkdir -p /opt/lexlink; cat > /opt/lexlink/.gemini_key'
fi

"${SSH[@]}" "BRANCH='$BRANCH' FRESH_SEED='$FRESH_SEED' NO_BUILD='$NO_BUILD' LOGS='$LOGS' bash -s" < "$SCRIPT_DIR/docker/deploy-remote.sh"
