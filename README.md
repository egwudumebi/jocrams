# JOCRAMS

**Journal of Communication Research and Media Studies** — an enterprise association management platform for [SICAMA](https://jocrams.com) (Scholars in Communication and Media Advancement Initiative).

JOCRAMS unifies public content, member self-service, admin operations, and a full scholarly journal workflow in a single Laravel + Vue application.

---

## Features

### Public website
- Branded corporate site with SEO, news, events, member directory, and static pages
- SICAMA parent-organization profile and editorial board
- Guest event registration with Paystack payment verification
- Public onboarding and membership credential verification
- Published journal browse and article access controls

### Member portal
- Registration, email verification, and membership applications
- Annual dues renewal with configurable renewal windows
- Event registration (member and guest flows)
- Digital membership credentials (card & certificate) with QR verification
- Journal manuscript submission (DOCX), revision resubmission, and payment
- Reviewer queue with accept/decline assignments and structured feedback
- Profile, payments, downloads, and support messaging

### Admin & journal manager
- Role-based access control (members, events, content, payments, settings)
- Membership approvals, renewals, and member management
- Event calendar, pricing tiers, registrations, and attendance
- News, pages, media library, and site-wide notification banner
- **Journal workflow:** calls for papers, volumes/issues, categories, fees catalog, editorial board, reviewer assignment, production editor upload, submission pipeline
- Payment ledger, reports, bulk campaigns, and audit logging

### Payments
- Paystack and Flutterwave integration
- Membership dues, event fees, journal submission fees
- Webhook reconciliation and admin payment visibility

---

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.3, Laravel 13, Sanctum |
| Frontend | Vue 3, Vue Router, Vite, Tailwind CSS 4 |
| Database | SQLite (dev & cPanel staging), PostgreSQL (Docker prod), MySQL/MariaDB (cPanel prod) |
| Cache / queues | Database, Redis (Docker prod) |
| PDF / exports | DomPDF, Maatwebsite Excel |
| CI | GitHub Actions |

---

## Project structure

```
app/                    Laravel application (API, services, domain logic)
resources/js/           Vue SPA (public, member, admin surfaces)
routes/api.php          REST API (v1: public, member, admin, journal)
database/               Migrations and seeders
docker/                 Production Docker stack (Nginx, PHP-FPM, Postgres, Redis)
docs/architecture/      System design and deployment guides
scripts/deploy.sh       Zero-downtime Docker deployment script
```

Three authenticated surfaces share one API:

| Surface | Path prefix | Audience |
|---------|-------------|----------|
| Public | `/` | Visitors, guests |
| Member | `/member` | Registered members |
| Admin | `/admin` | Staff and journal managers |

Journal manager routes use `/api/v1/journal/admin/*` with journal-specific permissions (`journal.assign`, `journal.review`, `journal.publish`, `journal.submit`).

---

## Requirements

- PHP **8.3+**
- Composer 2.x
- Node.js **20+** and npm (for frontend builds)
- SQLite, PostgreSQL, or MySQL/MariaDB

---

## Local development

```bash
# Install dependencies
composer install
npm ci

# Environment
cp .env.example .env
php artisan key:generate

# Database
touch database/database.sqlite   # if using SQLite
php artisan migrate
php artisan db:seed

# Storage symlink
php artisan storage:link

# Run (two terminals)
php artisan serve
npm run dev
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

**Seeded admin login** (after `RolePermissionSeeder`): check `database/seeders/RolePermissionSeeder.php` for default credentials in your environment.

### Docker (recommended for production-like setup)

```bash
make setup-docker
# Set APP_KEY in .env.docker, then:
make docker-up
make seed
```

See [docs/architecture/phase-5-deployment.md](docs/architecture/phase-5-deployment.md) for the full Docker stack.

---

## Testing

```bash
php artisan test
# or
make test
```

GitHub Actions runs Pint, PHPUnit, frontend build, and Docker image build on push.

---

## Production deployment

### Docker (VPS)

```bash
./scripts/deploy.sh production
```

Uses `docker-compose.yml` + `docker-compose.prod.yml` with Nginx, PHP-FPM, PostgreSQL, Redis, queue workers, and scheduler.

### Shared hosting (cPanel)

#### Phase 1 — SQLite staging (current)

The repo includes a seeded **`database/database.sqlite`** so you can smoke-test on cPanel before creating a MySQL database.

1. Set **PHP 8.3** in MultiPHP Manager for your domain.
2. Use CloudLinux alt-php for CLI: `/opt/alt/php83/usr/bin/php`
3. Clone the repo to e.g. `~/jocrams` and point the document root to `public/`
4. Copy env and generate a key:
   ```bash
   cp .env.cpanel.example .env
   php artisan key:generate
   ```
5. Ensure SQLite is writable:
   ```bash
   chmod 775 database
   chmod 664 database/database.sqlite
   ```
6. Run `composer install --no-dev`, `php artisan storage:link`, `php artisan config:cache`
7. Build frontend locally (`npm run build`) and upload/rsync `public/build/`
8. Add cron: `* * * * * cd ~/jocrams && php artisan schedule:run >> /dev/null 2>&1`

**Seeded staging login:** `admin@jocrams.test` / `password` (change before go-live).

> Do **not** run `migrate` on the bundled SQLite unless you intentionally want to apply new migrations — the file is already migrated and seeded.

**Quick server setup** (SSH into cPanel, after `git clone`):

```bash
cd ~/jocrams
bash scripts/deploy-cpanel.sh
```

**Fix generic “404 Not Found” (cPanel default page):** the domain document root must be `~/jocrams/public`, not `public_html` or the project root. In cPanel go to **Domains → jocrams.com → Document Root** and set it to `/home/calseries/jocrams/public`. Then open `https://jocrams.com/up` — you should see `{"status":"ok"}`.

#### Phase 2 — MySQL production

When ready to go live:

1. Create a MySQL database and user in cPanel
2. Update `.env`: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
3. Run `php artisan migrate --force` and optionally `php artisan db:seed`
4. Import or recreate content as needed; retire the SQLite file

---

## Configuration

Key environment variables (see `.env.example`):

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Canonical site URL (SEO, emails, payment callbacks) |
| `DB_*` | Database connection |
| `PAYSTACK_*` / `FLUTTERWAVE_*` | Payment gateways |
| `MAIL_*` | Transactional email |
| `QUEUE_CONNECTION` | `database` (shared hosting) or `redis` (Docker) |

Site branding (name, logo, SICAMA profile, notification banner) is managed via **Admin → System Settings** and seeded by `SicamaSeeder`.

---

## Documentation

- [System architecture](docs/architecture/phase-1-system-architecture.md)
- [Deployment & DevOps](docs/architecture/phase-5-deployment.md)
- [Implementation parity plan](docs/PARITY_IMPLEMENTATION_PLAN.md)

---

## License

MIT — see [LICENSE](LICENSE) if present. Application content and branding belong to SICAMA / JOCRAMS.
