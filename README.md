# PM Gateway (xPmGateway)

Integration layer between AI assistants (MCP clients) and a legacy PM system via browser automation.

## Architecture

```
MCP Clients → MCP Gateway (PHP/Nette) → Job Queue (MariaDB) → Worker (Node.js/Playwright) → Legacy PM
```

## Quick Start (DDEV)

### 1. DDEV Setup

```bash
ddev start
```

### 2. Adapter (PHP)

```bash
cd adapter
cp .env.template .env        # edit with your values
cp config/local.neon.template config/local.neon
ddev composer install -d adapter
```

Create the database and run migrations:

```bash
ddev exec mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS pm_gateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
ddev exec mysql -uroot -proot pm_gateway < adapter/migrations/001-initial-schema.sql
ddev exec mysql -uroot -proot pm_gateway < adapter/migrations/002-job-artifacts.sql
ddev exec mysql -uroot -proot pm_gateway < adapter/migrations/003-worker-heartbeats.sql
ddev exec mysql -uroot -proot pm_gateway < adapter/migrations/004-job-retry-reference.sql
```

Default admin login: `admin` / `admin123` (change immediately).

### 3. Worker (Node.js)

```bash
cd worker
cp .env.template .env        # edit with your values
ddev exec bash -c "cd /var/www/html/worker && npm install"
ddev exec npx playwright install chromium
ddev exec npx playwright install-deps chromium
ddev exec bash -c "cd /var/www/html/worker && npm run dev"    # development
# or
ddev exec bash -c "cd /var/www/html/worker && npm run build && npm start"  # production
```

### Without DDEV (Apache)

The root `.htaccess` routes all requests to `adapter/www/`. Make sure:
- `mod_rewrite` is enabled
- `AllowOverride All` is set for the document root
- For Worker: install Playwright browsers and system deps locally (`npx playwright install chromium && npx playwright install-deps chromium`)

## Project Structure

```
xPmGateway/
├── adapter/          PHP/Nette - MCP Gateway + Admin UI + Internal API
├── worker/           Node.js/Playwright - UI automation worker
├── packages/
│   └── contracts/    Shared JSON Schema contracts
├── tests/
│   └── e2e-rest.sh      E2E REST API test script
└── docs/
    ├── specification.md  System specification (CZ)
    ├── api.md            REST API reference
    ├── worker-handler-guide.md  How to write a new handler
    └── contracts.md      JSON Schema conventions
```

## Documentation

- **[REST API](docs/api.md)** — autentizace, endpointy, rate limiting, příklady curl
- **[Worker Handler Guide](docs/worker-handler-guide.md)** — jak napsat nový Playwright handler
- **[Contracts](docs/contracts.md)** — JSON Schema konvence a pravidla
- **[Specification](docs/specification.md)** — kompletní specifikace systému

## Quality & Testing

### Adapter (PHP)

```bash
cd adapter
composer check              # vše najednou (testy + PHPStan + PHPCS)
composer test               # unit testy (Nette Tester)
composer phpstan            # statická analýza
composer cs-check           # coding standard check
composer cs-fix             # auto-oprava coding standard
```

### Worker (Node.js)

```bash
cd worker
npm run check               # vše najednou (TypeScript + ESLint)
npm test                    # unit testy (Vitest)
npm run lint                # ESLint
```

### E2E testy

```bash
E2E_API_URL=https://gateway.example.com E2E_API_TOKEN=your-token ./tests/e2e-rest.sh
```

## MCP Tools (MVP)

| Tool | Description |
|------|-------------|
| `create_task` | Create a task in the legacy PM system |
| `get_job_status` | Check job completion status |
| `list_my_recent_jobs` | List recent jobs for a client |

## Admin UI

Available at `/admin/` with session-based authentication. Roles: `admin` (full access), `reader` (read-only).
