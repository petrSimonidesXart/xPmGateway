---
type: memory
category: decisions
id: DECISIONS-LOG
tags:
  - decisions
  - governance
created_at: 2026-03-22
updated_at: 2026-03-22
---

# Decision Log

> Append-only. Never delete or overwrite decisions.
> Only the Discovery Agent may add entries (or Bootstrap Agent during initialization).

---

## Next available ID: 7

| ID | Domain | Level | Title | Date |
|---|---|---|---|---|
| DEC-1 | architecture | strategic | Two-process architecture: PHP backend + Node.js worker | 2026-03-22 |
| DEC-2 | architecture | architectural | DB-backed job queue instead of message broker | 2026-03-22 |
| DEC-3 | architecture | architectural | Dual API surface: MCP Gateway + REST API v1 | 2026-03-22 |
| DEC-4 | architecture | architectural | Shared JSON Schema contracts between PHP and Node.js | 2026-03-22 |
| DEC-5 | architecture | architectural | Scenario engine for composable multi-step workflows | 2026-03-22 |
| DEC-6 | infrastructure | operational | Security model: Bearer tokens + service accounts + IP whitelist | 2026-03-22 |
