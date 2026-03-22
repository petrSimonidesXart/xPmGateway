---
id: DEC-1
domain: architecture
level: strategic
title: "Two-process architecture: PHP backend + Node.js worker"
status: active
created_by: bootstrap
created_at: 2026-03-22
last_updated_by: bootstrap
last_updated_at: 2026-03-22
supersedes: null
superseded_by: null
tags:
  - architecture
  - worker
  - separation-of-concerns
related_to: []
skills_invoked:
  - decision-extraction
---

# DEC-1 — Two-process architecture: PHP backend + Node.js worker

## Context

The legacy PM system has no API — the only integration path is UI automation via a browser. PHP is the team's primary backend language, while Playwright (Node.js) is the best tool for browser automation. These two capabilities need to be combined.

## Decision

Split the system into two separate processes:
- **PHP backend** (Nette): MCP gateway, REST API, admin UI, job queue management, auth, audit
- **Node.js worker**: Playwright-based browser automation, polls job queue via internal HTTP API

Communication is via a DB-backed job queue (`jobs` table in MariaDB) and an internal HTTP API secured with a shared secret.

## Impact

- Clear separation of concerns (API layer vs automation engine)
- Each process can be deployed, scaled, and restarted independently
- Worker failures don't crash the backend; backend outages don't affect running automation
- Adds operational complexity (two processes to manage, PM2 for worker)
- Internal API adds a network boundary with its own auth mechanism
