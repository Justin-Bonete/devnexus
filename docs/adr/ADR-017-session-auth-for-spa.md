# ADR-017: Session-cookie auth for the SPA (supersedes the Sanctum wording in ADR-014 for now)

Status: Accepted (Phase 1 / Milestone 1, Step 2)

- The React SPA is served same-origin (Vite dev proxy), so Laravel's built-in session guard + CSRF
  (XSRF-TOKEN cookie, `GET /api/auth/csrf`) gives the same security as Sanctum's SPA mode without
  depending on generated bootstrap files. API routes live in the `web` middleware group for that reason.
- Sanctum personal access tokens are added later, only for machine clients (agents/integrations);
  no endpoint changes are needed for that.
- Login is throttled (5/min), sessions are regenerated on login, disabled users cannot log in.
- Organization is chosen per request (`X-Organization-Id`, or the single membership); the role is
  per organization. Policies: owner/admin/project_manager write, owner/admin archive, all members read.
