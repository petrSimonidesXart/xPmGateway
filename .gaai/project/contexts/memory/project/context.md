---
type: memory
category: project
id: PROJECT-001
tags:
  - product
  - vision
  - scope
  - architecture
created_at: 2026-03-22
updated_at: 2026-03-22
skills_invoked:
  - codebase-scan
  - architecture-extract
  - memory-ingest
---

# Project Memory — xPmGateway

> This file is loaded at the start of every session. Keep it concise and high-signal.

---

## Project Overview

**Name:** xPmGateway (PM Gateway)

**Purpose:** Integration layer between AI assistants (ChatGPT, MCP clients, internal bots) and a legacy project management system that lacks an API. Uses MCP Gateway (JSON-RPC/SSE) + REST API + Playwright-based UI automation worker.

**Target Users:** Internal AI assistants and integrations that need to interact with the legacy PM system.

---

## Core Problems Being Solved

- Legacy PM system has no API — all interaction is through a web UI
- AI assistants need structured, vendor-neutral way to operate PM tasks
- Operations must be auditable, secure, and asynchronous

---

## Success Metrics

- Reliable tool execution via MCP/REST endpoints
- Full audit trail of all operations
- Secure multi-client access with per-tool permissions
- Scenario engine enables composable multi-step workflows

---

## Tech Stack & Conventions

- **Backend:** PHP 8.4, Nette Framework 3.2, MariaDB 11.8
- **Worker:** Node.js 20, TypeScript, Playwright
- **Templating:** Latte 3
- **Migrations:** Phinx (PHP)
- **Testing:** Nette Tester (PHP), Vitest (Node.js)
- **Static analysis:** PHPStan, ESLint
- **Code style:** nette/coding-standard (PHPCS/PHPCBF)
- **CI:** GitHub Actions (PHP quality + Worker quality)
- **Pre-commit:** Lefthook (PHPStan, PHPCS, ESLint, TSC)
- **Local dev:** DDEV (nginx-fpm, MariaDB 11.8)
- **JSON Schema:** Draft-07, shared contracts in `packages/contracts/`

---

## Architecture Overview

```
Clients (ChatGPT, MCP clients, REST clients)
         |
    +---------+---------+
    |                   |
MCP Gateway          REST API v1
(JSON-RPC/SSE)     (OpenAPI 3.1.0)
    |                   |
    +-------+-----------+
            |
      McpFacade (orchestration)
            |
      Job Queue (MariaDB `jobs` table)
            |
      Worker (Node.js + Playwright)
            |
      Legacy PM System (UI automation)
```

**Key architecture decisions:**
- Two-process model: PHP backend + Node.js worker
- DB-backed async job queue (not message broker)
- Hybrid response: sync if done within 20s, else queued
- Scenario engine: composable multi-step workflows with conditions, loops, template expressions
- Worker processes 1 job at a time (sequential, no parallelism)

---

## Module Boundaries

| Module | Responsibility |
|--------|---------------|
| `Module/Mcp` | MCP protocol (JSON-RPC/SSE, MCP 2024-11-05) |
| `Module/Api` | REST API v1, OpenAPI spec generation |
| `Module/Admin` | Admin UI (12 presenters, Latte templates, Naja AJAX) |
| `Module/Internal` | Internal API for worker (secret-based auth) |
| `Model/Service` | Business logic (9 services) |
| `Model/Facade` | Orchestration (McpFacade, JobFacade) |
| `Model/Repository` | DB access layer (12 repositories) |
| `apps/worker/tools` | 14 PM tool handlers + scenario runner |
| `packages/contracts` | Shared JSON Schema input/output contracts |

---

## Known Constraints

- No direct DB access to legacy system — only Playwright UI automation
- Worker runs single-threaded (1 job at a time)
- MCP endpoint is public (HTTPS) — tokens SHA-256 hashed, service account passwords AES-256 encrypted
- Root `.htaccess` ensures only `apps/backend/www/` is web-accessible
- Rate limiting: 60 req/min per-token + 10 failed auth/15min per-IP ban

---

## Out of Scope (Permanent)

- OAuth integration
- Webhook callback model
- Realtime progress streaming (beyond SSE keepalive)
- Multi-tenant support
- Direct DB access to legacy system
