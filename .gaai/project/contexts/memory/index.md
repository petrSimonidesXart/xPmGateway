---
type: memory_index
id: MEMORY-INDEX
updated_at: 2026-03-22
---

# Memory Map

> Always keep this index current. Agents use it to know what exists before calling `memory-retrieve`.
> Update when files are added, archived, or compacted.

---

## Shared Categories

| Category | Path | File count | Description |
|---|---|---|---|
| project | `project/` | 1 | Project-level facts, architecture, constraints |
| decisions | `decisions/` | 6 | Architecture Decision Records (ADRs) |
| patterns | `patterns/` | 1 | Coding conventions, procedural knowledge |
| summaries | `summaries/` | 0 | Compacted summaries of decisions/sessions |
| sessions | `sessions/` | 0 | Session-scoped working memory |
| archive | `archive/` | 0 | Archived/superseded memory |

---

## Active Files

| File | Category | ID | Last updated |
|---|---|---|---|
| `project/context.md` | project | PROJECT-001 | 2026-03-22 |
| `decisions/_log.md` | decisions | DECISIONS-LOG | 2026-03-22 |
| `decisions/DEC-1.md` | decisions | DEC-1 | 2026-03-22 |
| `decisions/DEC-2.md` | decisions | DEC-2 | 2026-03-22 |
| `decisions/DEC-3.md` | decisions | DEC-3 | 2026-03-22 |
| `decisions/DEC-4.md` | decisions | DEC-4 | 2026-03-22 |
| `decisions/DEC-5.md` | decisions | DEC-5 | 2026-03-22 |
| `decisions/DEC-6.md` | decisions | DEC-6 | 2026-03-22 |
| `patterns/conventions.md` | patterns | PATTERNS-001 | 2026-03-22 |

---

## Decision Registry

| DEC ID | Domain | Level | Description |
|---|---|---|---|
| DEC-1 | architecture | strategic | Two-process architecture: PHP backend + Node.js worker |
| DEC-2 | architecture | architectural | DB-backed job queue instead of message broker |
| DEC-3 | architecture | architectural | Dual API surface: MCP Gateway + REST API v1 |
| DEC-4 | architecture | architectural | Shared JSON Schema contracts between PHP and Node.js |
| DEC-5 | architecture | architectural | Scenario engine for composable multi-step workflows |
| DEC-6 | infrastructure | operational | Security model: Bearer tokens + service accounts + IP whitelist |

---

## Summaries

_(none yet)_

---

## Memory Principles

- **Retrieve selectively** — never load entire folders
- **Prefer summaries** over raw session notes
- **Archive aggressively** — move compacted content to `archive/`
- **Sessions are temporary** — always summarize before closing
- **Memory is distilled knowledge — not history**
