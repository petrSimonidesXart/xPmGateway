---
id: DEC-6
domain: infrastructure
level: operational
title: "Security model: Bearer tokens + service accounts + IP whitelist"
status: active
created_by: bootstrap
created_at: 2026-03-22
last_updated_by: bootstrap
last_updated_at: 2026-03-22
supersedes: null
superseded_by: null
tags:
  - security
  - authentication
  - authorization
related_to: [DEC-3]
skills_invoked:
  - decision-extraction
---

# DEC-6 — Security model: Bearer tokens + service accounts + IP whitelist

## Context

The MCP Gateway is publicly accessible. Access to legacy PM system requires credentials. Multiple clients with different permissions need to be supported.

## Decision

Multi-layer security model:
- **Authentication:** Bearer tokens (API keys), SHA-256 hashed in DB, prefix for identification
- **Authorization:** Per-client per-tool permissions (`client_permissions` table), IP whitelist (CIDR)
- **Rate limiting:** 60 req/min per-token (sliding window in DB), 10 failed auth/15min per-IP ban
- **Service accounts:** Credentials for legacy PM, AES-256 encrypted, decrypted only when passed to worker
- **Admin UI:** Session-based auth, role-based (admin/reader)
- **Internal API:** Shared secret token for worker-backend communication

## Impact

- Fine-grained access control per client/tool
- Defense in depth (token + permissions + IP + rate limit)
- Audit log captures all security events
- Service account passwords never stored in plaintext
