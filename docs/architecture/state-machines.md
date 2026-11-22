# State transition tables (single source; enforce in one domain service)

## Agent run (FR-AG-02)
| From | To | Trigger | Event |
|---|---|---|---|
| queued | planning | worker picks up | agent.run.started |
| queued | cancelled | cancel | agent.run.cancelled |
| planning | running | plan ok | agent.run.progress |
| running | awaiting_tool | tool call | agent.run.progress |
| awaiting_tool | running | tool result | agent.run.progress |
| running | awaiting_approval | gated tool | agent.run.awaiting_approval |
| awaiting_approval | running | approved | agent.run.progress |
| awaiting_approval | cancelled | rejected/expired | agent.run.cancelled |
| running | review | output produced | agent.run.progress |
| review | succeeded | criteria met | agent.run.succeeded |
| review | changes_requested | criteria not met | agent.run.progress |
| changes_requested | running | rework (counts vs max attempts) | agent.run.progress |
| running | failed | error | agent.run.failed |
| running | timed_out | max_run_seconds | agent.run.failed |
| running | cancelled | cancel | agent.run.cancelled |
| failed | queued | permitted retry (backoff, max attempts) | agent.run.started |
Terminal: succeeded, cancelled, timed_out.

## Task (FR-TASK-02)
backlog->ready (deps complete) | ready->assigned | assigned->running | running->blocked |
blocked->running | running->review | review->completed (evidence + criteria check, FR-TASK-06) |
review->changes_requested | changes_requested->running | any non-terminal->cancelled (human).
Run status never sets task=completed by itself; Leadman/reviewer does.

## Approval
pending -> approved | rejected | expired | invalidated (payload_hash changed).
Approved executes once via idempotency_key; result recorded; audit event written.

## Requirement (FR-REQ-04)
candidate -> clarification | proposed ; proposed -> approved | rejected ; approved -> superseded.
Approved requirements change only via change_requests (FR-REQ-09).
