---
id: DEC-3
domain: architecture
level: architectural
title: "Dual API surface: MCP Gateway + REST API v1"
status: active
created_by: bootstrap
created_at: 2026-03-22
last_updated_by: bootstrap
last_updated_at: 2026-03-22
supersedes: null
superseded_by: null
tags:
  - api
  - mcp
  - rest
  - vendor-neutral
related_to: [DEC-1]
skills_invoked:
  - decision-extraction
---

# DEC-3 — Dual API surface: MCP Gateway + REST API v1

## Context

The system needs to support both MCP-native clients (Claude, ChatGPT via MCP) and traditional REST integrations (Make.com, n8n, Zapier, ChatGPT Actions via OpenAPI).

## Decision

Expose two API surfaces:
- **MCP Gateway** (`/mcp`): JSON-RPC over SSE transport, MCP protocol 2024-11-05
- **REST API v1** (`/api/v1/*`): Standard REST endpoints with dynamically generated OpenAPI 3.1.0 spec

Both share the same backend: `McpFacade.handleToolCall()` is the single orchestration entry point.

## Impact

- Vendor-neutral: any MCP client or REST client can integrate
- Single business logic path — DRY, consistent behavior
- OpenAPI spec is dynamic (filtered by client permissions, enriched with lookup hints)
- Two transport layers to maintain, but thin wrappers over shared facade
