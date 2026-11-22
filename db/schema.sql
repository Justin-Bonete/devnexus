-- DEVNEXUS Phase 0/1 draft schema. UNTESTED DRAFT: no PostgreSQL available when written.
-- Convert to Laravel migrations; run against a real Postgres in CI before relying on it.
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE organizations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL, slug text NOT NULL UNIQUE,
  settings_json jsonb NOT NULL DEFAULT '{}', status text NOT NULL DEFAULT 'active',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now());

CREATE TABLE users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL, email text NOT NULL UNIQUE, password_hash text NOT NULL,
  mfa_secret_enc text, mfa_enabled boolean NOT NULL DEFAULT false,
  status text NOT NULL DEFAULT 'active',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now());

CREATE TABLE organization_members (
  organization_id uuid NOT NULL REFERENCES organizations, user_id uuid NOT NULL REFERENCES users,
  role text NOT NULL CHECK (role IN ('owner','admin','project_manager','developer','reviewer')),
  permissions_json jsonb NOT NULL DEFAULT '{}', status text NOT NULL DEFAULT 'active',
  PRIMARY KEY (organization_id, user_id));

CREATE TABLE invitations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, email text NOT NULL, role text NOT NULL,
  token_hash text NOT NULL, expires_at timestamptz NOT NULL, accepted_at timestamptz);

CREATE TABLE clients (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, name text NOT NULL,
  contact_json jsonb NOT NULL DEFAULT '{}', status text NOT NULL DEFAULT 'active',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now());

CREATE TABLE client_users (  -- client portal logins (separate guard)
  client_id uuid NOT NULL REFERENCES clients, user_id uuid NOT NULL REFERENCES users,
  organization_id uuid NOT NULL REFERENCES organizations, PRIMARY KEY (client_id, user_id));

CREATE TABLE projects (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, client_id uuid REFERENCES clients,
  name text NOT NULL, slug text NOT NULL, description text,
  status text NOT NULL DEFAULT 'discovery' CHECK (status IN
    ('discovery','requirements','planning','active','review','delivery','maintenance','archived')),
  health text, priority text, start_date date, due_date date,
  stack_json jsonb NOT NULL DEFAULT '{}', settings_json jsonb NOT NULL DEFAULT '{}',
  owner_user_id uuid REFERENCES users,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (organization_id, slug));

CREATE TABLE project_members (
  project_id uuid NOT NULL REFERENCES projects, user_id uuid NOT NULL REFERENCES users,
  organization_id uuid NOT NULL REFERENCES organizations,
  role text NOT NULL, access_scope text NOT NULL DEFAULT 'full', PRIMARY KEY (project_id, user_id));

CREATE TYPE visibility AS ENUM ('client','project_internal','tenant_internal','ceo_only','system');

CREATE TABLE conversations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid NOT NULL REFERENCES projects,
  channel_type text NOT NULL, visibility visibility NOT NULL,
  external_source text, external_id text, status text NOT NULL DEFAULT 'open', summary text,
  created_by uuid, created_at timestamptz NOT NULL DEFAULT now());

CREATE TABLE messages (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations,
  conversation_id uuid NOT NULL REFERENCES conversations,
  sender_type text NOT NULL CHECK (sender_type IN ('user','agent','system')),
  sender_user_id uuid REFERENCES users, sender_agent_id uuid,
  content text NOT NULL, content_format text NOT NULL DEFAULT 'text',
  visibility visibility NOT NULL, source_metadata_json jsonb NOT NULL DEFAULT '{}',
  idempotency_key text NOT NULL, processed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (conversation_id, idempotency_key));  -- FR-CHAT-03

CREATE TABLE message_attachments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, message_id uuid NOT NULL REFERENCES messages,
  storage_uri text NOT NULL, mime text, size_bytes bigint, checksum text,
  scan_status text NOT NULL DEFAULT 'pending' CHECK (scan_status IN ('pending','clean','infected','failed')));

CREATE TABLE requirements (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid NOT NULL REFERENCES projects,
  requirement_key text NOT NULL,
  type text NOT NULL CHECK (type IN ('functional','non_functional','business','constraint','assumption','risk','change')),
  title text NOT NULL, description text, priority text,
  status text NOT NULL DEFAULT 'candidate' CHECK (status IN
    ('candidate','clarification','proposed','approved','rejected','superseded')),
  confidence numeric(3,2), acceptance_criteria_json jsonb NOT NULL DEFAULT '[]',
  current_version int NOT NULL DEFAULT 1, created_by_agent_id uuid,
  approved_by_user_id uuid REFERENCES users, approved_at timestamptz,
  UNIQUE (project_id, requirement_key));

CREATE TABLE requirement_sources (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations,
  requirement_id uuid NOT NULL REFERENCES requirements,
  message_id uuid REFERENCES messages, document_id uuid,
  source_excerpt text, source_type text NOT NULL);

CREATE TABLE change_requests (  -- FR-REQ-09
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid NOT NULL REFERENCES projects,
  requirement_id uuid REFERENCES requirements, proposed_change_json jsonb NOT NULL,
  status text NOT NULL DEFAULT 'pending', decided_by uuid REFERENCES users, decided_at timestamptz);

CREATE TABLE trace_links (  -- FR-REQ-05 message->requirement->document->task->code/test
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid NOT NULL REFERENCES projects,
  from_type text NOT NULL, from_id uuid NOT NULL, to_type text NOT NULL, to_id uuid NOT NULL,
  link_type text NOT NULL, created_at timestamptz NOT NULL DEFAULT now());
CREATE INDEX ON trace_links (from_type, from_id); CREATE INDEX ON trace_links (to_type, to_id);

CREATE TABLE agent_templates (  -- global, no tenant data
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(), role text NOT NULL UNIQUE, title text NOT NULL,
  department text, default_prompt text, default_tool_policy_json jsonb NOT NULL DEFAULT '{}');

CREATE TABLE agents (  -- always tenant-owned
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations,
  template_id uuid REFERENCES agent_templates, name text NOT NULL, role text NOT NULL,
  department text, avatar_uri text, personality text, system_prompt_version int NOT NULL DEFAULT 1,
  provider_connection_id uuid, model_key text, tool_policy_json jsonb NOT NULL DEFAULT '{}',
  permissions_json jsonb NOT NULL DEFAULT '{}', status text NOT NULL DEFAULT 'offline',
  concurrency_limit int NOT NULL DEFAULT 1, budget_limit numeric(12,4), max_run_seconds int NOT NULL DEFAULT 600);

CREATE TABLE tasks (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid NOT NULL REFERENCES projects,
  requirement_id uuid REFERENCES requirements, title text NOT NULL, description text, task_type text,
  status text NOT NULL DEFAULT 'backlog' CHECK (status IN ('backlog','ready','assigned','running',
    'blocked','review','changes_requested','completed','cancelled')),
  priority text, assigned_agent_id uuid REFERENCES agents, assigned_user_id uuid REFERENCES users,
  acceptance_criteria_json jsonb NOT NULL DEFAULT '[]', due_at timestamptz, estimate numeric,
  actual_cost numeric(12,4) NOT NULL DEFAULT 0, created_by uuid, completed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  CHECK (NOT (assigned_agent_id IS NOT NULL AND assigned_user_id IS NOT NULL)));

CREATE TABLE task_dependencies (
  task_id uuid NOT NULL REFERENCES tasks, depends_on_task_id uuid NOT NULL REFERENCES tasks,
  organization_id uuid NOT NULL REFERENCES organizations, dependency_type text NOT NULL DEFAULT 'finish_to_start',
  PRIMARY KEY (task_id, depends_on_task_id), CHECK (task_id <> depends_on_task_id));
-- Cycle prevention: enforce in domain service (+ test); add trigger later.

CREATE TABLE agent_runs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid NOT NULL REFERENCES projects,
  agent_id uuid NOT NULL REFERENCES agents, task_id uuid REFERENCES tasks, parent_run_id uuid REFERENCES agent_runs,
  provider text, model_key text, prompt_version int, envelope_hash text,  -- FR-AG-12
  status text NOT NULL DEFAULT 'queued', attempt int NOT NULL DEFAULT 1, depth int NOT NULL DEFAULT 0,
  input_summary text, output_summary text, token_usage_json jsonb, estimated_cost numeric(12,4),
  error_code text, budget_snapshot_json jsonb, started_at timestamptz, finished_at timestamptz,
  CHECK (depth <= 3));

CREATE TABLE approvals (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL REFERENCES organizations, project_id uuid REFERENCES projects,
  entity_type text NOT NULL, entity_id uuid NOT NULL, action text NOT NULL,
  requested_by_agent_id uuid REFERENCES agents, payload_snapshot jsonb NOT NULL,
  payload_hash text NOT NULL, idempotency_key text NOT NULL UNIQUE,
  status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected','expired','invalidated')),
  requested_at timestamptz NOT NULL DEFAULT now(), expires_at timestamptz NOT NULL,
  decided_by_user_id uuid REFERENCES users, decided_at timestamptz, decision_note text, executed_at timestamptz);

CREATE TABLE event_outbox (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  organization_id uuid NOT NULL, project_id uuid, event_type text NOT NULL,
  aggregate_type text NOT NULL, aggregate_id uuid NOT NULL, actor_type text, actor_id uuid,
  correlation_id uuid, schema_version int NOT NULL DEFAULT 1, payload_json jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(), published_at timestamptz);
CREATE INDEX ON event_outbox (created_at) WHERE published_at IS NULL;

CREATE TABLE processed_events (
  consumer text NOT NULL, event_id uuid NOT NULL, processed_at timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (consumer, event_id));

CREATE TABLE audit_logs (
  id bigserial PRIMARY KEY, organization_id uuid NOT NULL, actor_type text NOT NULL, actor_id uuid,
  action text NOT NULL, entity_type text, entity_id uuid, ip_hash text,
  metadata_redacted_json jsonb NOT NULL DEFAULT '{}', prev_hash text, row_hash text,
  occurred_at timestamptz NOT NULL DEFAULT now());
CREATE FUNCTION audit_block_mutation() RETURNS trigger LANGUAGE plpgsql AS
$$ BEGIN RAISE EXCEPTION 'audit_logs is append-only'; END $$;
CREATE TRIGGER audit_no_update BEFORE UPDATE OR DELETE ON audit_logs
  FOR EACH ROW EXECUTE FUNCTION audit_block_mutation();
-- Also: REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM <app_role>;

-- Row-Level Security (apply to EVERY tenant table; shown for 3). App sets per request/job:
--   SET LOCAL app.current_org = '<uuid>';
ALTER TABLE projects ENABLE ROW LEVEL SECURITY; ALTER TABLE projects FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON projects
  USING (organization_id = current_setting('app.current_org', true)::uuid);
ALTER TABLE messages ENABLE ROW LEVEL SECURITY; ALTER TABLE messages FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON messages
  USING (organization_id = current_setting('app.current_org', true)::uuid);
ALTER TABLE tasks ENABLE ROW LEVEL SECURITY; ALTER TABLE tasks FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON tasks
  USING (organization_id = current_setting('app.current_org', true)::uuid);
