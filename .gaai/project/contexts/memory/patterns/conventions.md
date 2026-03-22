---
type: memory
category: patterns
id: PATTERNS-001
tags:
  - patterns
  - conventions
  - procedural
created_at: 2026-03-22
updated_at: 2026-03-22
skills_invoked:
  - codebase-scan
  - architecture-extract
  - memory-ingest
---

# Patterns & Conventions

> Procedural memory: how things are done in this project.
> Agent-maintained. Updated when durable patterns are confirmed.
> The Delivery Agent loads this before every implementation task.

---

## Code Patterns

### PHP (Backend)

- **Autoloading:** PSR-4, namespace `App\` maps to `app/`
- **Presenter mapping:** `App\Module\*\Presenters\*Presenter`
- **Service layer:** Pure business logic in `Model/Service/`, no DB access
- **Repository layer:** All DB access through `Model/Repository/`, extends `BaseRepository`
- **Facade pattern:** `McpFacade` orchestrates tool calls (auth check, permission check, rate limit, schema validation, job creation, audit log)
- **DI config:** `services.neon` registers all services/repos; `common.neon` for parameters
- **Error handling:** `McpException` with HTTP code mapping to JSON-RPC error codes
- **Nette Forms:** Used in Admin UI presenters for CRUD operations

### TypeScript (Worker)

- **ESM modules** (`"type": "module"` in package.json)
- **Tool registry:** `toolRegistry` in `registry.ts` maps tool names to handler functions
- **Handler signature:** `ToolFunction = (job, api) => Promise<void>`
- **Standalone vs scenario:** Tools run either standalone (own browser) or within scenario (shared browser context)
- **Template expressions:** `{{input.field}}`, `{{stepId.output.field}}` parsed by `templateParser.ts`

---

## Test Patterns

### PHP

- **Framework:** Nette Tester (`*.phpt` files)
- **Location:** `apps/backend/tests/` organized by layer: `Service/`, `Facade/`, `Api/`, `Templates/`, `Config/`
- **Run:** `cd apps/backend && composer test` or `vendor/bin/tester tests -C`
- **CI runs:** `vendor/bin/tester tests/Service tests/Facade tests/Api tests/Templates -C`

### TypeScript

- **Framework:** Vitest (`*.test.ts` files)
- **Location:** `apps/worker/src/__tests__/`
- **Run:** `cd apps/worker && npm test`

---

## Architecture Patterns

- **Async job queue:** DB-backed (`jobs` table), not message broker
- **Hybrid response model:** Sync if done within ~20s poll, otherwise return `mode: queued` with `job_id`
- **Scenario engine:** Multi-step workflows with conditions (`if/then/else`), loops (`over/as`), template expressions
- **Disambiguation flow:** `awaiting_input` state when tool finds ambiguous results; client must choose
- **Contract-driven:** JSON Schema in `packages/contracts/` validates both PHP and Node.js sides
- **OpenAPI generation:** Dynamic, filtered by client permissions

---

## Anti-Patterns (Avoid)

- Never access legacy PM DB directly — always through Playwright UI automation
- Never store plaintext passwords — AES-256 encryption with key from env
- Never log full tokens — only `token_prefix` (first 8 chars)
- Never skip audit logging for any MCP/API/admin action
- Never process multiple jobs in parallel in the worker
