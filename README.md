# PM Gateway (xPmGateway)

Integration layer between AI assistants (MCP clients) and a legacy PM system via browser automation.

## Architecture

```
MCP Clients → MCP Gateway (PHP/Nette) → Job Queue (MariaDB) → Worker (Node.js/Playwright) → Legacy PM
```

## Quick Start

### 1. Adapter (PHP)

```bash
cd adapter
cp .env.template .env        # edit with your values
cp config/local.neon.template config/local.neon
composer install
```

Create the database and run migrations:

```bash
mysql -u root -e "CREATE DATABASE pm_gateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
# Run migration via nextras/migrations or manually:
mysql -u root pm_gateway < migrations/001-initial-schema.sql
```

Default admin login: `admin` / `admin123` (change immediately).

### 2. Worker (Node.js)

```bash
cd worker
cp .env.template .env        # edit with your values
npm install
npx playwright install chromium
npm run dev                  # development
# or
npm run build && npm start   # production
```

### 3. Apache Configuration

The root `.htaccess` routes all requests to `adapter/www/`. Make sure:
- `mod_rewrite` is enabled
- `AllowOverride All` is set for the document root

## Project Structure

```
xPmGateway/
├── adapter/          PHP/Nette - MCP Gateway + Admin UI + Internal API
├── worker/           Node.js/Playwright - UI automation worker
├── packages/
│   └── contracts/    Shared JSON Schema contracts
└── docs/
    └── specification.md
```

## MCP Tools (MVP)

| Tool | Description |
|------|-------------|
| `create_task` | Create a task in the legacy PM system |
| `get_job_status` | Check job completion status |
| `list_my_recent_jobs` | List recent jobs for a client |

## Admin UI

Available at `/admin/` with session-based authentication. Roles: `admin` (full access), `reader` (read-only).
