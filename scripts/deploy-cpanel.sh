#!/usr/bin/env bash
# cPanel deploy helper — run ON THE SERVER after cloning the repo.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP="${PHP:-php}"
if command -v /opt/alt/php83/usr/bin/php >/dev/null 2>&1; then
    PHP="/opt/alt/php83/usr/bin/php"
fi

echo "==> JOCRAMS cPanel setup ($ROOT)"
echo "    PHP: $($PHP -v | head -1)"

if [[ ! -f .env ]]; then
    cp .env.cpanel.example .env
    echo "==> Created .env from .env.cpanel.example"
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    $PHP artisan key:generate --force
fi

chmod 775 database storage bootstrap/cache 2>/dev/null || true
chmod 664 database/database.sqlite 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [[ ! -d vendor ]]; then
    composer install --no-dev --optimize-autoloader --no-interaction
fi

$PHP artisan storage:link --force 2>/dev/null || true
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo ""
echo "Done. Ensure your domain document root points to:"
echo "  $ROOT/public"
echo ""
echo "In cPanel: Domains → jocrams.com → Document Root → set to the path above."
echo "Then visit https://jocrams.com/up to verify Laravel is running."
