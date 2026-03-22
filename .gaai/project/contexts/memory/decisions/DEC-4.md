---
id: DEC-4
domain: architecture
level: architectural
title: "Shared JSON Schema contracts between PHP and Node.js"
status: active
created_by: bootstrap
created_at: 2026-03-22
last_updated_by: bootstrap
last_updated_at: 2026-03-22
supersedes: null
superseded_by: null
tags:
  - contracts
  - validation
  - json-schema
related_to: [DEC-1, DEC-3]
skills_invoked:
  - decision-extraction
---

# DEC-4 — Shared JSON Schema contracts between PHP and Node.js

## Context

Two separate runtimes (PHP backend, Node.js worker) handle the same data. Input validation and API contract definition need to be consistent.

## Decision

Store shared JSON Schema contracts in `packages/contracts/`. Naming convention: `{tool-name}.input.json` / `{tool-name}.output.json`. Draft-07, `additionalProperties: false` on inputs. Backend validates inputs before creating jobs. OpenAPI spec embeds these schemas dynamically.

## Impact

- Single source of truth for data contracts
- Backend validates before job creation; worker can trust payload shape
- Adding a new tool requires creating contract files — not code changes to validation logic
- OpenAPI spec auto-includes new tools when contracts exist
