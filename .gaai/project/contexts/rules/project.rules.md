---
type: rules
category: project
id: RULES-PROJECT-001
track: cross-cutting
updated_at: 2026-03-22
skills_invoked:
  - rules-normalize
---

# Project Rules — xPmGateway

These rules are derived from existing project conventions and configurations.
They apply to all agents working on this project.

---

## Code Quality

### PHP (Backend)

1. **PHPStan level 5** — all PHP code must pass PHPStan analysis at level 5. Config: `apps/backend/phpstan.neon`. Baseline: `phpstan-baseline.neon`.
2. **Nette Coding Standard** — all PHP code must conform to `nette/coding-standard` (PHPCS with Nette preset + PHP 8.4 preset). Config: `apps/backend/phpcs.xml`.
3. **Tabs for indentation** — tab-width 4 (enforced by PHPCS).
4. **`declare(strict_types=1)`** — required in all PHP files.

### TypeScript (Worker)

5. **TypeScript strict mode** — `"strict": true` in tsconfig.json.
6. **ESLint recommended + typescript-eslint recommended** — `@typescript-eslint/no-explicit-any` at warn level.
7. **ESM modules** — `"type": "module"` in package.json, use `.js` extensions in imports.

---

## Testing

8. **PHP tests required for services, facades, API:** Nette Tester, `*.phpt` files in `apps/backend/tests/`.
9. **Worker tests required for core logic:** Vitest, `*.test.ts` files in `apps/worker/src/__tests__/`.
10. **CI runs all tests on push/PR to main:** GitHub Actions (`ci.yml`).

---

## Pre-commit Hooks (Lefthook)

11. **Pre-commit:** PHPStan, PHPCS, PHPCBF (auto-fix), ESLint, TSC — run in parallel on staged files.
12. **Pre-push:** PHP tests (`tester tests -C`), Worker tests (`vitest run`).

---

## Security

13. **Tokens must be hashed** (SHA-256) — never stored in plaintext.
14. **Service account passwords must be AES-256 encrypted** — encryption key from env.
15. **Audit log is append-only** — no UPDATE, no DELETE on `audit_log` table.
16. **All MCP/API/admin actions must be audit-logged.**
17. **Rate limiting must be enforced** — per-token (60/min) and per-IP (10 failed auth/15min).
18. **Internal API must use shared secret** — `INTERNAL_API_SECRET` in env.

---

## Contracts

19. **Every tool must have JSON Schema contracts** — `packages/contracts/{tool-name}.input.json` + `{tool-name}.output.json`.
20. **Input schemas must have `additionalProperties: false`.**
21. **JSON Schema draft-07.**

---

## Architecture

22. **No direct DB access to legacy system** — only Playwright UI automation.
23. **Worker processes 1 job at a time** — no parallel job execution.
24. **New browser context per job** — session isolation.
25. **McpFacade is the single orchestration entry point** — both MCP and REST API use it.

---

## Infrastructure

26. **DDEV for local development** — PHP 8.4, MariaDB 11.8, nginx-fpm.
27. **Phinx for database migrations** — config in `apps/backend/db/phinx.php`, migrations in `apps/backend/db/migrations/`.
28. **GitHub CLI uses work account** — `petrSimonidesXart` via `GH_TOKEN`.
