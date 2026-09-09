# Phase 5: Production Deployment & DevOps Strategy

## Overview

Jocrams ships with a production-ready Docker stack, Redis-backed caching/queues, PostgreSQL, Nginx reverse proxy, and GitHub Actions CI/CD.

```
                    ┌─────────────┐
   Internet ───────►│    Nginx    │ :80
                    └──────┬──────┘
                           │ fastcgi
                    ┌──────▼──────┐
                    │  PHP-FPM    │ (app)
                    │  Laravel 13 │
                    └──┬───────┬──┘
                       │       │
              ┌────────▼─┐  ┌─▼────────┐
              │ Postgres │  │  Redis   │
              └──────────┘  └────┬─────┘
                                 │
                    ┌────────────┼────────────┐
                    │            │            │
              ┌─────▼─────┐ ┌───▼───┐ ┌──────▼──────┐
              │   Queue   │ │Scheduler│ │ Cache/Session│
              │  Workers  │ │ Worker  │ │              │
              └───────────┘ └─────────┘ └──────────────┘
```

---

## 1. Quick Start (Docker)

```bash
# First-time setup
make setup-docker

# Generate APP_KEY and add to .env.docker
php artisan key:generate --show

# Restart after setting APP_KEY
docker compose up -d

# Seed demo data
docker compose exec app php artisan db:seed
```

**Access:** http://localhost:8080

---

## 2. Docker Services

| Service | Image | Purpose |
|---------|-------|---------|
| `app` | `jocrams/app` | PHP 8.3-FPM, Laravel application |
| `nginx` | `jocrams/nginx` | Reverse proxy, static assets, gzip |
| `postgres` | `postgres:16-alpine` | Primary database |
| `redis` | `redis:7-alpine` | Cache, sessions, queues |
| `queue` | `jocrams/app` | `queue:work redis` |
| `scheduler` | `jocrams/app` | `schedule:work` (renewal reminders) |

### Production overrides

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

---

## 3. Environment Configuration

Copy `.env.docker.example` → `.env.docker`:

| Variable | Production Value |
|----------|-----------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `pgsql` |
| `CACHE_STORE` | `redis` |
| `SESSION_DRIVER` | `redis` |
| `QUEUE_CONNECTION` | `redis` |
| `RUN_MIGRATIONS` | `true` |

---

## 4. Redis Caching Strategy

| Layer | Driver | TTL | Purpose |
|-------|--------|-----|---------|
| Config/Routes/Views | OPcache + artisan cache | Until deploy | Bootstrap performance |
| Application cache | Redis | Configurable | Query results, API responses |
| Sessions | Redis | 120 min | Member/Admin auth sessions |
| Queues | Redis | Persistent | Payments, emails, bulk SMS |
| Rate limiting | Redis | Per throttle config | API abuse prevention |

Redis is configured with `maxmemory 256mb` and `allkeys-lru` eviction policy.

---

## 5. Database Optimization

### PostgreSQL tuning (auto-applied via `docker/postgres/init.sql`)

- `shared_buffers = 256MB`
- `effective_cache_size = 768MB`
- `work_mem = 8MB`
- Indexed columns on all queue tables (Phase 1)

### Recommended maintenance

```bash
# Weekly (cron on host)
docker compose exec postgres vacuumdb -U jocrams -d jocrams -z -v

# Analyze query performance
docker compose exec app php artisan db:monitor
```

---

## 6. Zero-Downtime Deployment

```bash
./scripts/deploy.sh production
```

**Deployment flow:**

1. Build new Docker image
2. Enable maintenance mode (`artisan down --secret=...`)
3. Start updated containers (migrations run via entrypoint)
4. Health check polling on `/up`
5. Warm config/route/view caches
6. Graceful queue worker restart (`queue:restart`)
7. Disable maintenance mode

**Bypass maintenance:** `https://yoursite.com/jocrams-deploy`

---

## 7. CI/CD Pipeline (GitHub Actions)

File: `.github/workflows/ci.yml`

| Job | Trigger | Actions |
|-----|---------|---------|
| `test` | PR + push | Pint lint, PHPUnit, migrations against Postgres + Redis |
| `frontend` | PR + push | `npm ci && npm run build` |
| `docker` | push to `main` | Multi-stage Docker build with layer cache |
| `deploy` | push to `main` | Production deploy (configure secrets) |

### Required GitHub Secrets (production deploy)

| Secret | Description |
|--------|-------------|
| `DEPLOY_HOST` | Server IP or hostname |
| `DEPLOY_USER` | SSH user |
| `DEPLOY_SSH_KEY` | Private key for deployment |

---

## 8. Nginx Configuration

File: `docker/nginx/default.conf`

- SPA fallback to `index.php` (Vue Router history mode)
- API routes via `/api/*`
- Gzip compression for JS/CSS/JSON
- Security headers (X-Frame-Options, nosniff, etc.)
- Static asset caching (1 year, immutable)
- 25MB upload limit
- Health check at `/up`

### SSL/TLS (production)

Mount certificates in `docker-compose.prod.yml`:

```yaml
nginx:
  volumes:
    - ./docker/nginx/ssl:/etc/nginx/ssl:ro
```

Add to nginx config:

```nginx
listen 443 ssl http2;
ssl_certificate     /etc/nginx/ssl/fullchain.pem;
ssl_certificate_key /etc/nginx/ssl/privkey.pem;
```

---

## 9. cPanel / Apache Setup (Alternative)

For shared hosting without Docker:

```apache
<VirtualHost *:80>
    ServerName jocrams.example.com
    DocumentRoot /home/user/jocrams/public

    <Directory /home/user/jocrams/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Proxy PHP via FPM or mod_php
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/jocrams-error.log
    CustomLog ${APACHE_LOG_DIR}/jocrams-access.log combined
</VirtualHost>
```

Ensure `.htaccess` in `public/` (Laravel default) handles SPA routing.

**Cron (cPanel):**
```
* * * * * cd /home/user/jocrams && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker (Supervisor on VPS):**
```ini
[program:jocrams-worker]
command=php /var/www/jocrams/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
```

---

## 10. Monitoring & Health Checks

| Endpoint | Purpose |
|----------|---------|
| `GET /up` | Laravel health check (DB, Redis, disk) |
| Docker healthchecks | Container orchestration |
| `php artisan queue:monitor` | Queue depth alerts |

### Recommended production monitoring

- **Uptime:** Ping `/up` every 60s
- **Logs:** `docker compose logs -f app queue`
- **Errors:** Sentry/Bugsnag integration (optional)
- **Metrics:** Prometheus + Grafana (optional)

---

## 11. Backup Strategy

```bash
# Database backup
docker compose exec postgres pg_dump -U jocrams jocrams | gzip > backup-$(date +%F).sql.gz

# Storage backup
docker compose exec app tar czf - storage/app > storage-$(date +%F).tar.gz
```

Schedule daily backups via cron with 30-day retention.

---

## Phase 5 Checklist

- [x] Multi-stage Dockerfile (Node → Composer → PHP-FPM → Nginx)
- [x] Docker Compose stack (app, nginx, postgres, redis, queue, scheduler)
- [x] Production compose overrides
- [x] Nginx routing with security headers and gzip
- [x] Redis cache/session/queue configuration
- [x] PostgreSQL performance tuning
- [x] Zero-downtime deploy script
- [x] GitHub Actions CI/CD pipeline
- [x] cPanel/Apache alternative documentation
- [x] Makefile for common operations
