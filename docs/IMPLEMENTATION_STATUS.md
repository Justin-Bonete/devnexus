# DEVNEXUS implementation status

Rule: nothing is "done" until its test passes on a clean machine.

## Phase 0
- [x] Compose (Postgres + Redis), ADR-011..015, state machines, draft schema

## Phase 1 / Milestone 1 (foundation)
- [ ] Step 1: schema v2, DB roles, RLS everywhere, composite FKs, TenantContext, isolation tests, Laravel 13
      -> delivered, awaiting your confirmation (scripts/setup.ps1 -Reset)
- [ ] Step 2: session auth, organizations, clients, projects, role policies, hash-chained audit log
      -> delivered, untested (scripts/apply-overlay.ps1)
- [ ] Step 3: React + Vite scaffold (same-origin proxy), login + project list screens, CI pipeline

## Next
M2 chat/outbox/mock AI/requirement extraction -> M3 planner/tasks/runs/approvals -> M4 SRS docs/notifications/budgets
