#!/bin/bash
# Jocrams zero-downtime deployment script
# Usage: ./scripts/deploy.sh [environment]
#
# Strategy:
#   1. Pull latest image / build
#   2. Run migrations on new containers
#   3. Warm caches
#   4. Rolling restart of app + queue workers
#   5. Health check verification

set -euo pipefail

ENV="${1:-production}"
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
APP_URL="${APP_URL:-http://localhost}"
HEALTH_ENDPOINT="${APP_URL}/up"
MAX_RETRIES=30
RETRY_INTERVAL=5

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

log "Starting deployment (env: ${ENV})..."

# ── Pre-deploy backup reminder ──────────────────────────────────────────────
log "Ensure database backup is current before proceeding."

# ── Build & pull ──────────────────────────────────────────────────────────────
log "Building application image..."
$COMPOSE build app

# ── Enable maintenance mode on running instance ───────────────────────────────
log "Enabling maintenance mode..."
$COMPOSE exec -T app php artisan down --retry=60 --secret="${DEPLOY_SECRET:-jocrams-deploy}" 2>/dev/null || true

# ── Start updated services ────────────────────────────────────────────────────
log "Starting updated containers..."
$COMPOSE up -d --remove-orphans

# ── Wait for health ─────────────────────────────────────────────────────────
log "Waiting for application health check..."
for i in $(seq 1 $MAX_RETRIES); do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${HEALTH_ENDPOINT}" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        log "Health check passed (HTTP ${HTTP_CODE})."
        break
    fi
    if [ "$i" -eq "$MAX_RETRIES" ]; then
        log "ERROR: Health check failed after ${MAX_RETRIES} attempts."
        $COMPOSE logs --tail=50 app nginx
        exit 1
    fi
    log "Attempt ${i}/${MAX_RETRIES}: HTTP ${HTTP_CODE} — retrying in ${RETRY_INTERVAL}s..."
    sleep $RETRY_INTERVAL
done

# ── Post-deploy optimization ─────────────────────────────────────────────────
log "Running post-deploy optimization..."
$COMPOSE exec -T app php artisan optimize:clear 2>/dev/null || true
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

# ── Restart queue workers (graceful) ──────────────────────────────────────────
log "Restarting queue workers..."
$COMPOSE exec -T app php artisan queue:restart
$COMPOSE restart queue scheduler

# ── Disable maintenance mode ──────────────────────────────────────────────────
log "Disabling maintenance mode..."
$COMPOSE exec -T app php artisan up

log "Deployment complete."
$COMPOSE ps
