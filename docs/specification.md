# Projekt: PM Adapter + MCP Gateway + Playwright Worker

**Typ dokumentu:** Zadání pro implementačního agenta\
**Verze:** 1.1\
**Stav:** Schválená architektura (MVP scope)

------------------------------------------------------------------------

# 1. Cíl systému

Vytvořit integrační vrstvu mezi:

-   interními AI asistenty (ChatGPT, jiní MCP klienti, interní boti)
-   a legacy projektovým systémem (bez API)

Integrace bude probíhat pomocí:

-   veřejné MCP gateway
-   interní Adapter API (Nette)
-   asynchronní fronty úloh
-   Worker služby (Node.js + Playwright), která provádí UI automatizaci

Systém musí být:

-   bezpečný
-   auditovatelný
-   asynchronní
-   škálovatelný
-   vendor-neutral (nezávislý na konkrétním AI klientovi)

------------------------------------------------------------------------

# 2. High-Level Architektura

```
Klienti (ChatGPT / jiný MCP klient)
        │
        ▼
MCP Gateway (public HTTPS, SSE transport)
        │
        ▼
Adapter API (Nette, intranet)
        │
        ▼
Fronta úloh (DB tabulka `jobs`)
        │
        ▼
Worker (Node.js + Playwright)
  - polluje frontu přes interní HTTP API adapteru
  - vrací výsledky přes interní HTTP API adapteru
        │
        ▼
Legacy projektový systém (PHP Web UI, UI automatizace)
```

------------------------------------------------------------------------

# 3. Technologický stack

## Backend (Adapter + MCP)

-   PHP 8.x
-   Nette Framework
-   REST API
-   MCP server endpoint (součást aplikace)
-   DB: MariaDB

## Worker

-   Node.js (LTS)
-   Playwright
-   Headless režim
-   Samostatný proces na stejném serveru

## PHP balíčky (composer.json)

Jádro:

-   `nette/application` — presentery, routing, šablony
-   `nette/bootstrap` — bootstrap, konfigurace
-   `nette/di` — dependency injection
-   `nette/http` — HTTP request/response
-   `nette/security` — autentizace, autorizace (admin login)
-   `nette/forms` — formuláře v admin UI
-   `nette/database` — DB přístup (Database Explorer)
-   `nette/caching` — cache
-   `nette/utils` — utility (Json, Strings, Random, ...)
-   `nette/schema` — validace konfigurací a vstupů
-   `nette/mail` — emailové alerty při chybě jobu
-   `latte/latte` — šablonovací engine
-   `tracy/tracy` — debugger, error logging

Třetí strany:

-   `nextras/migrations` — DB migrace (SQL soubory)
-   `contributte/console` — CLI příkazy (seed, cron, migrace)
-   `opis/json-schema` — validace payloadů proti JSON Schema kontraktům

MCP protokol: vlastní lightweight implementace (JSON-RPC přes SSE).

## Node.js balíčky (package.json)

-   `playwright` — UI automatizace
-   `ajv` — JSON Schema validace (kontrakty)
-   `dotenv` — .env konfigurace
-   `typescript` — dev dependency

## Infra

-   Monorepo
-   LAMP server s Apache (intranet)
-   Worker jako samostatný Node.js proces na stejném serveru
-   Reverse proxy / Apache (HTTPS terminace pro MCP endpoint)
-   Rate limiting
-   Audit log

## Admin UI

-   Součást Nette aplikace
-   Naja (AJAX) + Latte snippety
-   Autentizace admin uživatelů (session)
-   CRUD klientů, tokenů, service accountů
-   Přehled jobů a audit logu

------------------------------------------------------------------------

# 4. Repo struktura (monorepo)

Document root webserveru je nastaven na root celého projektu (dáno serverem).
Kořenový `.htaccess` směruje požadavky do odpovídajících adresářů.

```
xPmGateway/
├── .htaccess                        -- routing do adapter/www
├── adapter/
│   ├── app/
│   │   ├── Bootstrap.php
│   │   ├── Model/
│   │   │   ├── Entity/              -- entity (Client, Job, Tool, ...)
│   │   │   ├── Repository/          -- DB přístup
│   │   │   ├── Service/             -- business logika
│   │   │   │   ├── AuthService.php
│   │   │   │   ├── JobService.php
│   │   │   │   ├── AuditService.php
│   │   │   │   ├── EncryptionService.php
│   │   │   │   └── RateLimitService.php
│   │   │   └── Facade/             -- orchestrace (MCP → service → job)
│   │   ├── Module/
│   │   │   ├── Admin/
│   │   │   │   ├── Presenters/
│   │   │   │   └── templates/
│   │   │   ├── Mcp/
│   │   │   │   ├── Presenters/      -- MCP endpoint
│   │   │   │   └── Transport/       -- SSE handling
│   │   │   └── Internal/
│   │   │       └── Presenters/      -- API pro worker
│   │   └── Router/
│   │       └── RouterFactory.php
│   ├── config/
│   │   ├── common.neon
│   │   ├── local.neon               -- .gitignore, z .env
│   │   ├── local.neon.template
│   │   └── services.neon
│   ├── migrations/
│   ├── storage/
│   │   ├── screenshots/
│   │   └── log/
│   ├── www/
│   │   ├── index.php
│   │   ├── .htaccess
│   │   └── assets/                  -- CSS/JS (Naja)
│   ├── .env.template
│   └── composer.json
│
├── worker/
│   ├── src/
│   │   ├── index.ts
│   │   ├── handlers/
│   │   │   └── createTask.ts
│   │   └── lib/
│   │       ├── api.ts
│   │       ├── auth.ts
│   │       └── screenshots.ts
│   ├── .env.template
│   ├── package.json
│   └── tsconfig.json
│
├── packages/
│   └── contracts/
│       ├── create-task.input.json
│       ├── create-task.output.json
│       ├── get-job-status.input.json
│       ├── get-job-status.output.json
│       ├── list-my-recent-jobs.input.json
│       └── list-my-recent-jobs.output.json
│
├── docs/
│   └── specification.md
│
└── README.md
```

Kořenový `.htaccess` přesměruje všechny HTTP požadavky do `adapter/www/`,
který je vstupním bodem Nette aplikace. Ostatní adresáře (worker, packages,
docs) nejsou přes web přístupné.

------------------------------------------------------------------------

# 5. MCP Gateway

## 5.1 Charakteristika

-   Veřejně dostupný endpoint (HTTPS)
-   Součást Nette aplikace
-   Implementuje MCP protokol
-   Ověřuje tokeny
-   Řeší autorizaci
-   Volá interní service layer

## 5.2 Autentizace

-   Bearer token (API klíč)
-   Každý klient (registrovaná aplikace) má vlastní API token(y)
-   Token:
    -   uložen hashovaný (SHA-256) v DB
    -   prefix (prvních 8 znaků) pro identifikaci v admin UI
    -   má expiraci (volitelně)
    -   je možné ho revokovat
    -   zobrazí se pouze jednou při vytvoření

## 5.3 Autorizace

Permission-based přístup na úrovni klienta:

-   Každý klient má explicitně přiřazené povolené tooly
-   IP whitelist — volitelné omezení, odkud smí klient volat (CIDR notace)

Neoprávněné volání:

-   401 -- neplatný / expirovaný / revokovaný token
-   403 -- nedostatečné oprávnění (tool není povolen) nebo IP mimo whitelist

------------------------------------------------------------------------

# 6. Asynchronní model zpracování

Systém je primárně asynchronní.

## 6.1 Lifecycle jobu

Stavy:

pending\
processing\
success\
failed\
timeout

## 6.2 Flow

1.  MCP přijme tool call
2.  Adapter validuje vstup, ověří oprávnění klienta
3.  Vytvoří job v DB (status=pending), zapíše audit log
4.  Vrátí:
    -   buď výsledek (pokud dokončeno do krátkého timeoutu)
    -   nebo job_id + status=queued
5.  Worker polluje `GET /api/internal/jobs/next`, dostane nejstarší pending job
6.  Worker provede UI automatizaci v legacy systému
7.  Worker vrátí výsledek přes `POST /api/internal/jobs/{id}/result`
8.  Adapter uloží výsledek do DB

## 6.3 Retry strategie

-   Max 3 pokusy na job
-   Backoff: 30s → 60s → 120s
-   Po vyčerpání pokusů: status=failed
-   Timeout na zpracování: 120s (konfigurovatelné per-job)
-   Cron úloha periodicky označuje joby v processing déle než timeout

------------------------------------------------------------------------

# 7. Hybridní model odpovědi

Tool `create_task`:

-   Pokusí se čekat až 15--25 sekund na dokončení
-   Pokud dokončeno → vrátí `mode: done`
-   Pokud ne → vrátí `mode: queued` + `job_id`

Nikdy nevrací `success`, pokud akce reálně nebyla provedena v PM
systému.

------------------------------------------------------------------------

# 8. MCP Tools (MVP)

## 8.1 create_task

Input:

-   title (string, required)
-   project (string, required)
-   assignee (string, optional)
-   due_date (string, optional)
-   estimate_hours (number, optional)

Output:

{ mode: "done" \| "queued", job_id?: string, task_id?: string, status:
"pending" \| "processing" \| "success" \| "failed" }

------------------------------------------------------------------------

## 8.2 get_job_status

Input:

-   job_id (string)

Output:

{ status, result?, error?, finished_at? }

------------------------------------------------------------------------

## 8.3 list_my_recent_jobs

Vrací poslední joby daného klienta.

Input:

-   limit (integer, optional, default 10, max 50)
-   status (string, optional — filtr na stav jobu)
-   tool_name (string, optional — filtr na tool)

Output:

{ jobs: [{ job_id, tool_name, status, created_at, finished_at? }] }

------------------------------------------------------------------------

# 8A. JSON Schema kontrakty

Sdílené kontrakty v `packages/contracts/`. Validují se na obou stranách
(Adapter: PHP, Worker: Node.js).

Soubory:

-   `create-task.input.json`
-   `create-task.output.json`
-   `get-job-status.input.json`
-   `get-job-status.output.json`
-   `list-my-recent-jobs.input.json`
-   `list-my-recent-jobs.output.json`

Schémata používají JSON Schema draft-07, `additionalProperties: false` na vstupech.

------------------------------------------------------------------------

# 9. Databázové schéma

## 9.1 `admin_users` — přihlášení do admin UI

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `username` | string, unique | |
| `password_hash` | string | bcrypt |
| `role` | enum(`admin`, `reader`) | admin = plný přístup, reader = jen čtení |
| `is_active` | bool | |
| `last_login_at` | timestamp, nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## 9.2 `clients` — registrované aplikace

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `name` | string | název aplikace |
| `description` | text, nullable | popis účelu |
| `is_active` | bool | možnost deaktivovat |
| `service_account_id` | FK → service_accounts | účet pro legacy systém |
| `allowed_ips` | json, nullable | IP whitelist (CIDR), null = bez omezení |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## 9.3 `api_tokens` — API klíče

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `client_id` | FK → clients | |
| `token_hash` | string(64) | SHA-256 hash |
| `token_prefix` | string(8) | prvních 8 znaků pro identifikaci |
| `label` | string, nullable | "produkční", "testovací" |
| `expires_at` | timestamp, nullable | null = neexpiruje |
| `revoked_at` | timestamp, nullable | null = aktivní |
| `last_used_at` | timestamp, nullable | |
| `created_at` | timestamp | |

## 9.4 `service_accounts` — credentials do legacy systému

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `name` | string | "Bot tým vývoje" |
| `username` | string | |
| `password_encrypted` | text | AES-256, klíč v env |
| `is_active` | bool | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Base URL legacy systému je v konfiguraci (.env), společná pro všechny účty.

## 9.5 `tools` — evidence MCP toolů

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `name` | string, unique | `create_task` |
| `description` | string | popis toolu |
| `is_active` | bool | možnost dočasně vypnout |
| `created_at` | timestamp | |

## 9.6 `client_permissions` — oprávnění klient × tool

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `client_id` | FK → clients | |
| `tool_id` | FK → tools | |
| `created_at` | timestamp | |

Unique constraint na (`client_id`, `tool_id`).

## 9.7 `jobs` — fronta úloh

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | uuid PK | UUID — bezpečné vracet klientovi |
| `client_id` | FK → clients | |
| `service_account_id` | FK → service_accounts | |
| `tool_id` | FK → tools | |
| `payload` | json | vstupní parametry |
| `status` | enum | `pending`, `processing`, `success`, `failed`, `timeout` |
| `result` | json, nullable | výstup z workeru |
| `error_message` | text, nullable | |
| `screenshots` | json, nullable | pole screenshotů `[{step, file}, ...]` |
| `attempts` | int, default 0 | počet pokusů |
| `max_attempts` | int, default 3 | |
| `created_at` | timestamp | |
| `started_at` | timestamp, nullable | kdy worker začal |
| `finished_at` | timestamp, nullable | |
| `timeout_seconds` | int, default 120 | max doba zpracování |
| `retry_of_job_id` | uuid FK → jobs, nullable | odkaz na původní job při opakování |

## 9.8 `audit_log` — append-only záznam všech akcí

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | bigint PK | |
| `created_at` | timestamp | |
| `client_id` | FK → clients, nullable | null pro admin akce |
| `client_name` | string | denormalizovaný (čitelný i po smazání klienta) |
| `api_token_id` | FK → api_tokens, nullable | |
| `tool_name` | string | denormalizovaný |
| `action` | string | `mcp_call`, `admin_login`, `token_created`, `token_revoked`, ... |
| `payload` | json, nullable | vstupní data |
| `result_status` | string | `success`, `failed`, `queued`, `denied` |
| `result_data` | json, nullable | zkrácená odpověď / chyba |
| `job_id` | FK → jobs, nullable | pokud akce vytvořila job |
| `ip_address` | string | |
| `user_agent` | string, nullable | identifikace volajícího |
| `duration_ms` | int, nullable | doba zpracování |

Audit log je append-only — žádné UPDATE, žádné DELETE.

## 9.9 `worker_heartbeats` — heartbeaty worker procesu

| Sloupec | Typ | Popis |
|---|---|---|
| `worker_id` | string(64) PK | identifikátor workeru (default: `main`) |
| `last_seen_at` | datetime | čas posledního pollu |
| `started_at` | datetime | čas startu/restartu workeru |

Heartbeat se zaznamenává implicitně při každém `GET /api/internal/jobs/next`.
Admin UI zobrazuje stav: idle (zelená), busy (modrá), offline (červená), nepřipojen (šedá).

## 9.10 `rate_limits` — sliding window countery

| Sloupec | Typ | Popis |
|---|---|---|
| `id` | int PK | |
| `key` | string, index | `token:{token_id}` nebo `ip:{address}` |
| `hits` | int | počet požadavků v okně |
| `window_start` | timestamp | začátek aktuálního okna |

------------------------------------------------------------------------

# 10. Adapter API odpovědnosti

-   Validace vstupních dat (proti JSON Schema kontraktům)
-   Identifikace klienta z API tokenu
-   Ověření oprávnění (tool + IP whitelist)
-   Audit log všech akcí
-   Enqueue job
-   Evidence výsledku
-   Interní API pro worker:
    -   `GET /api/internal/jobs/next` — vrátí nejstarší pending job
    -   `POST /api/internal/jobs/{id}/result` — worker vrací výsledek
-   Interní API zabezpečeno sdíleným secret tokenem

Adapter nikdy přímo neprovádí UI automatizaci.

------------------------------------------------------------------------

# 11. Worker (Playwright)

## 11.1 Charakteristika

-   Samostatný Node.js proces na stejném serveru
-   Polluje frontu přes interní HTTP API adapteru
-   Zpracovává jeden job naráz (žádný paralelismus)
-   Pro každý job vytváří nový Playwright `BrowserContext` (izolované cookies/session)
-   Přihlašuje se servisním účtem z jobu, po dokončení context zavírá

## 11.2 Struktura

```
worker/
  src/
    index.ts            — polling loop, orchestrace
    handlers/
      createTask.ts     — Playwright kroky pro vytvoření tasku
    lib/
      auth.ts           — přihlášení do legacy systému
      api.ts            — komunikace s Adapter API
      screenshots.ts    — ukládání screenshotů
```

## 11.3 Video nahrávání

Worker automaticky nahrává průběh každého jobu jako WebM video (Playwright `recordVideo`).

- Rozlišení: 1280x720
- Video se finalizuje po `context.close()`
- Upload jako artifact (`video/webm`) přes interní API
- V admin UI se přehrává inline `<video>` elementem
- Lze vypnout: `RECORD_VIDEO=0` v `.env` workeru

## 11.4 Screenshot log

Worker pořizuje screenshoty při **každém jobu** (úspěch i chyba) z klíčových kroků:

1. Po přihlášení
2. Po navigaci na cílovou stránku
3. Po vyplnění formuláře
4. Po odeslání
5. Výsledek / chybová stránka

Ukládání:
-   Filesystem: `storage/screenshots/{job_id}/01-login-ok.png`
-   V DB: `jobs.screenshots` — JSON pole `[{step: "login", file: "01-login-ok.png"}, ...]`
-   Admin UI: route `/admin/jobs/{id}/screenshot/{filename}` pro zobrazení

## 11.5 Zásady UI automatizace

-   Nepoužívat poziční selektory
-   Preferovat stabilní atributy (id, name, data-\*)
-   Ošetřit timeouty
-   Retry pouze řízené
-   Nový BrowserContext pro každý job (izolace session)

------------------------------------------------------------------------

# 12. Bezpečnostní požadavky

-   MCP veřejně pouze přes HTTPS
-   Tokeny hashované (SHA-256)
-   Možnost revokace tokenů
-   Worker a PM systém nejsou veřejně dostupné
-   Service account hesla šifrovaná (AES-256), klíč v env
-   Kořenový `.htaccess` zajišťuje, že přes web je přístupný pouze `adapter/www/`

## 12.1 Rate limiting

### Per-token
-   60 req/min na API token (konfigurovatelné)
-   Při překročení: HTTP 429 + `Retry-After` header + audit log záznam

### Per-IP (anti brute-force)
-   10 neúspěšných autentizací / 15 min na IP adresu
-   Po překročení: dočasný ban IP na 15 minut

### Implementace
-   Sliding window counter v DB (tabulka `rate_limits`)
-   `rate_limits`: `id`, `key` (string, index), `hits` (int), `window_start` (timestamp)
-   Lazy cleanup starých záznamů

## 12.2 Audit
-   Audit všech MCP volání (úspěch i neúspěch)
-   Audit všech admin akcí (login, CRUD operace, job_cancelled, job_retried)
-   Audit bezpečnostních událostí (neplatné tokeny, rate limit, IP ban)

------------------------------------------------------------------------

# 13. Monitoring a alerting

-   Logování každého jobu
-   Ukládání chyb
-   Metriky:
    -   doba zpracování
    -   počet failů
    -   počet pending jobů

## 13.1 Emailový alert při chybě jobu

-   Při selhání jobu (status=failed) systém odešle email na logovací adresu
-   Adresa v `.env`: `ALERT_EMAIL=admin@example.com`
-   Obsah emailu: job ID, tool, klient, chybová zpráva, timestamp
-   Implementace: `nette/mail` (SmtpMailer nebo SendmailMailer)
-   SMTP konfigurace v `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`)

## 13.2 Worker health monitoring

-   Implicitní heartbeat: adapter zaznamenává čas každého `GET /api/internal/jobs/next`
-   Tabulka `worker_heartbeats` — UPSERT s auto-restart detekcí (gap > 30s → reset `started_at`)
-   Admin UI: worker status bar v layoutu (zelená=idle, modrá+pulz=busy, červená=offline, šedá=nepřipojen)
-   Status bar je expandovatelný — uptime, naposledy viděn, aktuální job s odkazem
-   Stav `busy` se odvozuje z tabulky `jobs` (existuje processing job)
-   Cron skript `scripts/cron-maintenance.php` (každou minutu):
    -   Spouští `processTimeouts()` nezávisle na workeru
    -   Posílá email alert při offline workeru (rate-limited: max 1× za 10 minut)

## 13.3 Job management z admin UI

-   **Cancel** (`job_cancelled`): zruší pending/processing job, nastaví status=failed
-   **Retry** (`job_retried`): vytvoří nový job se stejnými parametry, propojení přes `retry_of_job_id`
-   Obě akce se logují do audit logu
-   V job detail view se zobrazuje retry chain oběma směry (původní ↔ nový)

------------------------------------------------------------------------

# 14. Ne-funkční požadavky

-   Oddělení vrstev (MCP ≠ business logika)
-   Škálovatelnost workerů
-   Vendor-neutral přístup (libovolný MCP klient)
-   Žádný přímý přístup do DB legacy systému
-   Žádné falšování výsledků

------------------------------------------------------------------------

# 15. Admin UI

## 15.1 Obrazovky

| Obrazovka | Cesta | Role | Popis |
|---|---|---|---|
| Login | `/admin/login` | všichni | přihlášení |
| Dashboard | `/admin/` | admin, reader | přehled (pending/failed joby, poslední aktivita) |
| Clients — seznam | `/admin/clients` | admin, reader | tabulka klientů |
| Client — detail/edit | `/admin/clients/{id}` | admin | editace, oprávnění, IP whitelist |
| Client — nový | `/admin/clients/create` | admin | formulář |
| API Tokens | `/admin/clients/{id}/tokens` | admin | seznam, generování, revokace |
| Service Accounts — seznam | `/admin/service-accounts` | admin, reader | |
| Service Account — edit | `/admin/service-accounts/{id}` | admin | |
| Tools — seznam | `/admin/tools` | admin, reader | seznam toolů, admin může toggle active/inactive |
| Jobs — seznam | `/admin/jobs` | admin, reader | tabulka s filtry (status, klient, tool, datum) |
| Job — detail | `/admin/jobs/{id}` | admin, reader | payload, výsledek, chyba, screenshot galerie, video přehrávání, retry chain |
| Audit Log | `/admin/audit-log` | admin, reader | filtrovaný seznam (klient, akce, datum) |
| Admin Users | `/admin/users` | admin | správa admin uživatelů |

## 15.2 Technologie

-   Nette presentery + Latte šablony
-   Naja pro AJAX (snippety)
-   Nette Forms
-   Reader role: vidí vše, nemůže editovat, nevidí citlivá data (hesla, plné tokeny)

------------------------------------------------------------------------

# 16. Konfigurace

## 16.1 `.env` (Adapter — PHP)

```
APP_ENV=production

DB_HOST=localhost
DB_NAME=pm_gateway
DB_USER=pm_gateway
DB_PASSWORD=

LEGACY_PM_BASE_URL=https://pm.interni-sit.cz

ENCRYPTION_KEY=base64:...32-byte-random...

INTERNAL_API_SECRET=dlouhy-nahodny-string

RATE_LIMIT_PER_MINUTE=60

ALERT_EMAIL=admin@example.com
SMTP_HOST=localhost
SMTP_PORT=25
SMTP_USER=
SMTP_PASSWORD=
```

## 16.2 `config/local.neon`

```neon
parameters:
    database:
        host: %env.DB_HOST%
        name: %env.DB_NAME%
        user: %env.DB_USER%
        password: %env.DB_PASSWORD%

    legacyPm:
        baseUrl: %env.LEGACY_PM_BASE_URL%

    security:
        encryptionKey: %env.ENCRYPTION_KEY%
        internalApiSecret: %env.INTERNAL_API_SECRET%
        rateLimitPerMinute: %env.RATE_LIMIT_PER_MINUTE%

    alerting:
        email: %env.ALERT_EMAIL%

    smtp:
        host: %env.SMTP_HOST%
        port: %env.SMTP_PORT%
        user: %env.SMTP_USER%
        password: %env.SMTP_PASSWORD%
```

## 16.3 `.env` (Worker — Node.js)

```
ADAPTER_API_URL=http://localhost/api/internal
INTERNAL_API_SECRET=dlouhy-nahodny-string
POLL_INTERVAL_MS=5000
SCREENSHOT_DIR=./storage/screenshots
RECORD_VIDEO=1
```

Debug mód Nette se řeší v `Bootstrap.php` detekcí prostředí (IP/hostname).

------------------------------------------------------------------------

# 17. Explicitně mimo scope (MVP)

-   OAuth integrace
-   Webhook callback model
-   Realtime progress stream
-   Multi-tenant podpora
-   Položky z backlogu (sekce 19)

------------------------------------------------------------------------

# 18. Backlog (post-MVP fáze)

## 18.1 Sentry integrace
-   Error tracking a alerting přes Sentry
-   PHP SDK (`sentry/sentry`) + Nette integrace
-   Worker: Node.js Sentry SDK (`@sentry/node`)
-   Nahradí/doplní emailové alerty o kontextově bohatší monitoring

## 18.2 Testy — Adapter (PHP)
-   Nette Tester
-   Unit testy: service vrstva, validace, šifrování
-   Integrační testy: MCP endpoint, interní API, autentizace, rate limiting
-   Testovací DB (in-memory SQLite nebo testovací MariaDB)

## 18.3 Testy — Worker (Node.js)
-   Vitest nebo Jest
-   Unit testy: handlery, API client, screenshot logic
-   Integrační testy: mock Adapter API, Playwright testy proti testovací stránce

## 18.4 Statická analýza a coding standards
-   PHPStan (level 5+, postupně zvyšovat)
-   Nette Coding Standard (`nette/coding-standard`) — PHP CS Fixer pravidla
-   ESLint + Prettier pro worker (TypeScript)
-   CI pipeline: lint + stan + testy při každém push

------------------------------------------------------------------------

# 19. Očekávaný výstup od implementačního agenta

-   Monorepo struktura
-   Funkční MCP endpoint (SSE transport)
-   Implementované 3 MCP tooly
-   DB migrace (všechny tabulky ze sekce 9)
-   JSON Schema kontrakty (packages/contracts/)
-   Základní Playwright worker s pollováním
-   Admin UI (Nette + Naja): správa klientů, tokenů, přehled jobů, audit log
-   Konfigurace pro LAMP server (Apache)
-   README s instrukcí spuštění
