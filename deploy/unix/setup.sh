#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.env"

echo "[gm-landing] project root: ${PROJECT_ROOT}"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "[gm-landing] missing .env at ${ENV_FILE}" >&2
  exit 1
fi

echo "[gm-landing] .env already exists"

mkdir -p "${PROJECT_ROOT}/runtime/logs/cron"

chmod 750 "${PROJECT_ROOT}/runtime" "${PROJECT_ROOT}/runtime/logs" "${PROJECT_ROOT}/runtime/logs/cron" 2>/dev/null || true
chmod 640 "${ENV_FILE}" 2>/dev/null || true

cat <<'EOF'
[gm-landing] next steps:
1. Edit .env with your live domain and DB credentials.
2. Install the Apache or Nginx sample config from deploy/unix/.
3. Point your web server document root at /var/www/graymentality.
4. Reload PHP-FPM and the web server.
EOF
