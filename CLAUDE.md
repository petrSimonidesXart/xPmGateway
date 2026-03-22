# xPmGateway

## Project Structure

- `apps/backend/` — PHP/Nette backend (MCP Gateway, REST API, Admin UI)
- `apps/worker/` — Node.js/Playwright worker (browser automation)
- `packages/contracts/` — JSON Schema input contracts
- `docs/` — documentation

## GitHub

- GitHub CLI (`gh`) je nakonfigurované přes `GH_TOKEN` env proměnnou v `.claude/settings.local.json`
- Token patří work účtu **petrSimonidesXart** — veškeré `gh` operace (PR, issues, releases) běží pod tímto účtem
- Git push/pull používá SSH alias `github-work` (nastavený v SSH config)
- Při vytváření PR nebo interakci s GitHub API není potřeba přepínat účty — vše je automatické

## Commands

All commands MUST be run via composer/npm scripts. Never call vendor/bin/* or npx directly.

### Backend (apps/backend/)

- `composer test` — run tests (Nette Tester)
- `composer phpstan` — static analysis
- `composer cs-check` — coding standard check
- `composer cs-fix` — auto-fix coding standard
- `composer check` — run all checks (test + phpstan + cs-check)
- `composer migrate` — run DB migrations (Phinx)
- `composer migrate:status` — migration status
- `composer migrate:rollback` — rollback last migration

### Worker (apps/worker/)

- `npm test` — run tests (Vitest)
- `npm run typecheck` — TypeScript type check
- `npm run lint` — ESLint
- `npm run check` — run all checks (typecheck + lint)
- `npm run build` — build for production
- `npm run dev` — run in development mode
