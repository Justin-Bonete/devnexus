# Phase 0 decisions (assumptions - reversible, owner to confirm)

Status: PROPOSED. Each is a safe default per spec section 0 item 14.

## ADR-011 Laravel-only start (refines ADR-009)
Agent/provider logic lives in Laravel modules behind interfaces (ProviderAdapter, ToolGateway,
Orchestrator). Contracts are JSON Schema in packages/agent-contracts. Split to a Node service
only if a provider SDK or isolation need justifies it.

## ADR-012 Multi-tenant ready, single org at first
Every tenant-owned table has organization_id. PostgreSQL Row-Level Security is a second layer
behind Laravel policies. Cross-tenant tests gate every milestone.

## ADR-013 Postgres is the source of truth for events
Writes + event_outbox row in ONE transaction. A relay publishes to Redis queues. Consumers record
(consumer, event_id) in processed_events for idempotency. Redis loss must not lose work.

## ADR-014 Auth: Laravel Sanctum cookie (SPA) + separate client-portal guard
Owner/staff and client users are distinct guards. MFA (TOTP) hooks in Phase 1, enforced for owner.

## ADR-015 Budget reserve-then-settle
Before any provider call: atomically reserve estimated cost against the budget row; settle with
actual usage after. Prevents overshoot under concurrent runs.

## Untrusted-input rule (extends FR-SEC-04)
Agents that read raw client text, files or repo content run with NO tools and emit schema-validated
output only. A separate privileged agent (Leadman) acts on validated output.
