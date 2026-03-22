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

## Migrations

- Phinx (PHP) — config v `apps/backend/db/phinx.php`, migrace v `apps/backend/db/migrations/`
- Spuštění: `cd apps/backend && composer migrate`
