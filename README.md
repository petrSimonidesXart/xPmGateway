# PM Gateway (xPmGateway)

Integration layer between AI assistants (MCP clients, ChatGPT) and a legacy PM system via browser automation.

## Architecture

```
AI Clients (MCP/REST) → Gateway (PHP/Nette) → Job Queue (MariaDB) → Worker (Node.js/Playwright) → Legacy PM
                              ↕
                         Admin UI (Tabler)
```

**Key concepts:**
- **Tools** — atomic browser operations (login, open project, create comment, ...)
- **Scenarios** — composable chains of tools with conditions and loops
- **Lookups** — configurable shortcuts for people (PS→Petr Simonides), labels, dates

## Quick Start (DDEV)

```bash
ddev start
```

### Adapter (PHP)

```bash
cd adapter
cp .env.template .env
cp config/local.neon.template config/local.neon
ddev composer install -d adapter
```

Run all migrations:

```bash
for f in adapter/migrations/*.sql; do ddev mysql -D pm_gateway < "$f"; done
```

Default admin login: `admin` / `admin123` (change immediately).

### Worker (Node.js)

```bash
cd worker
cp .env.template .env
npm install
npx playwright install chromium
npx playwright install-deps chromium
npm run dev          # development (tsx)
# or
npm run build && npm start  # production
```

## Project Structure

```
xPmGateway/
├── adapter/              PHP/Nette — MCP Gateway + REST API + Admin UI
│   ├── app/Module/
│   │   ├── Admin/        Admin UI (presenters + Latte templates)
│   │   ├── Mcp/          MCP JSON-RPC endpoint
│   │   ├── Api/          REST API v1 (OpenAPI/ChatGPT Actions)
│   │   └── Internal/     Internal API for worker
│   ├── migrations/       SQL migrations (001–011)
│   └── scripts/          Test & maintenance scripts
├── worker/               Node.js/Playwright — browser automation
│   └── src/
│       ├── tools/        Tool infrastructure
│       │   ├── pm/       PM tool implementations (14 tools)
│       │   ├── registry.ts
│       │   ├── scenarioRunner.ts
│       │   ├── standaloneRunner.ts
│       │   └── templateParser.ts
│       └── lib/          Shared utilities (API client, auth, video)
├── packages/
│   └── contracts/        JSON Schema input contracts
└── docs/                 Documentation
```

## Documentation

- **[REST API](docs/api.md)** — authentication, endpoints, rate limiting
- **[Scenarios](docs/scenarios.md)** — how to create and use scenarios
- **[Worker Handler Guide](docs/worker-handler-guide.md)** — how to write a Playwright handler
- **[Contracts](docs/contracts.md)** — JSON Schema conventions

## PM Tools

| Tool | Description |
|------|-------------|
| `pm_login` | Přihlášení do PM aplikace |
| `pm_open_project` | Vyhledá a otevře projekt (search + disambiguace) |
| `pm_open_task` | Vyhledá a otevře úkol v projektu |
| `pm_read_task` | Přečte detaily úkolu |
| `pm_update_task` | Upraví pole úkolu |
| `pm_close_task` | Dokončí úkol |
| `pm_create_comment` | Přidá komentář k úkolu |
| `pm_create_subtask` | Vytvoří podúkol (podpora zkratek osob, štítků, termínů) |
| `pm_search_comments` | Vyhledá v komentářích |
| `pm_search_subtasks` | Vyhledá v podúkolech |
| `pm_close_subtask` | Zavře podúkol |
| `pm_update_subtask` | Upraví podúkol |
| `pm_time_track` | Vykáže čas na úkolu |
| `pm_export_csv` | Exportuje úkoly/výkazy jako CSV |

## Scenarios

Scenarios chain tools into reusable workflows. Example: "add comment to task" = login → open project → open task → create comment.

- Created and managed in Admin UI → Scénáře
- Callable via MCP as `scenario_{name}` or REST API as `POST /api/v1/tools/scenario_{name}`
- Support conditions (`if/then/else`), loops (`for each`), template expressions (`{{input.project}}`)
- Auto-login before execution (no need for `pm_login` step)

See [docs/scenarios.md](docs/scenarios.md) for details.

## Lookup Shortcuts

Configurable in Admin UI → Číselníky. Used in tool inputs:

| Category | Example | Resolves to |
|----------|---------|-------------|
| People | `PS` | Petr Simonides |
| Labels | `RESIT` | ŘEŠIT |
| Schedule | `TT`, `PT`, `DNES` | this_week, next_week, today's date |

## API Integration

### MCP (Claude Desktop, Cursor)

```json
{
  "mcpServers": {
    "pm-gateway": {
      "url": "https://your-gateway.com/mcp",
      "headers": { "Authorization": "Bearer YOUR_TOKEN" }
    }
  }
}
```

### REST API (ChatGPT Actions)

OpenAPI spec: `GET /api/v1/openapi.json` (requires Bearer token, shows only permitted tools).

## Admin UI

Available at `/admin/` with session-based authentication.

| Section | Description |
|---------|-------------|
| Dashboard | KPI karty, poslední úlohy a aktivita |
| Klienti | Správa klientů, oprávnění, tokeny |
| Service Accounts | PM přihlašovací údaje + URL (dev/prod) |
| Nástroje | PM tooly — otestovat / vytvořit scénář |
| Úlohy | Job list s paginací, řetězy, live progress |
| Scénáře | CRUD scénářů, step builder, spuštění |
| Číselníky | Zkratky osob, štítků, termínů |
| Audit log | Log všech akcí |

## Quality & Testing

```bash
# Adapter (PHP)
cd adapter
composer check    # tests + PHPStan + PHPCS

# Worker (Node.js)
cd worker
npm run check     # TypeScript + ESLint
npm test          # Vitest

# MCP integration test
ddev exec "cd /var/www/html/adapter && bash scripts/test-mcp.sh"
```
