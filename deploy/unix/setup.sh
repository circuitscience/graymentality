#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.env"
ENV_EXAMPLE="${PROJECT_ROOT}/.env.example"

echo "[gm-landing] project root: ${PROJECT_ROOT}"

if [[ ! -f "${ENV_FILE}" ]]; then
  if [[ ! -f "${ENV_EXAMPLE}" ]]; then
    echo "[gm-landing] missing .env.example at ${ENV_EXAMPLE}" >&2
    exit 1
  fi

  cp "${ENV_EXAMPLE}" "${ENV_FILE}"
  echo "[gm-landing] created .env from .env.example"
else
  echo "[gm-landing] .env already exists"
fi

mkdir -p "${PROJECT_ROOT}/runtime/logs"

chmod 750 "${PROJECT_ROOT}/runtime" "${PROJECT_ROOT}/runtime/logs" 2>/dev/null || true
chmod 640 "${ENV_FILE}" 2>/dev/null || true

cat <<'EOF'
[gm-landing] next steps:
1. Edit .env with your live domain and DB credentials.
2. Install the Apache or Nginx sample config from deploy/unix/.
3. Point your web server document root at /var/www/graymentality.
4. Reload PHP-FPM and the web server.
EOF
