---
id: DEC-5
domain: architecture
level: architectural
title: "Scenario engine for composable multi-step workflows"
status: active
created_by: bootstrap
created_at: 2026-03-22
last_updated_by: bootstrap
last_updated_at: 2026-03-22
supersedes: null
superseded_by: null
tags:
  - scenarios
  - workflow
  - composition
related_to: [DEC-1, DEC-3]
skills_invoked:
  - decision-extraction
---

# DEC-5 — Scenario engine for composable multi-step workflows

## Context

Individual PM tools (open project, open task, create comment) need to be chained into complex workflows. Each step may depend on previous outputs.

## Decision

Implement a scenario engine with:
- Stored scenarios in DB (`scenarios` table) with JSON steps definition
- Step types: `tool`, `condition` (if/then/else), `loop` (over/as)
- Template expressions: `{{input.field}}`, `{{stepId.output.field}}`
- Auto-login: shared browser session across all steps
- Scenarios exposed as regular tools via MCP/REST (`scenario_{name}`)
- Disambiguation: `awaiting_input` state when ambiguous results found

## Impact

- Non-developers can create complex workflows through Admin UI
- Scenarios are first-class citizens in the API (appear as tools)
- Template parser enables data flow between steps
- Adds complexity to worker (scenarioRunner.ts) but eliminates need for per-workflow code
