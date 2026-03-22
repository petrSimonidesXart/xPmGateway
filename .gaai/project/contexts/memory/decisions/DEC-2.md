---
id: DEC-2
domain: architecture
level: architectural
title: "DB-backed job queue instead of message broker"
status: active
created_by: bootstrap
created_at: 2026-03-22
last_updated_by: bootstrap
last_updated_at: 2026-03-22
supersedes: null
superseded_by: null
tags:
  - architecture
  - job-queue
  - infrastructure
related_to: [DEC-1]
skills_invoked:
  - decision-extraction
---

# DEC-2 — DB-backed job queue instead of message broker

## Context

The system needs asynchronous job processing. Options considered: Redis/RabbitMQ message broker vs. DB-backed queue.

## Decision

Use the MariaDB `jobs` table as the job queue. Worker polls the backend's internal API (`GET /api/internal/jobs/next`) to fetch pending jobs. No external message broker.

## Impact

- Simpler infrastructure: no additional service to deploy/maintain
- Jobs have rich metadata (status, attempts, retry chain, screenshots, step_results)
- Natural audit trail — job history is queryable
- Worker polls rather than subscribes — slightly higher latency (~5s poll interval)
- Sufficient for current scale (single worker, sequential processing)
- Timeout detection via cron script (`scripts/cron-maintenance.php`)
