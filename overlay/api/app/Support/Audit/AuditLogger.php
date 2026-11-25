<?php

namespace App\Support\Audit;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Append-only, per-organization hash-chained audit trail (FR-SEC audit, ADR-012). */
final class AuditLogger
{
    private const REDACT = ['password', 'password_confirmation', 'token', 'secret', 'authorization', 'api_key'];

    public static function record(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        string $actorType = 'user',
        ?string $actorId = null,
    ): void {
        $org = TenantContext::current() ?? throw new RuntimeException('Audit requires a tenant context.');
        $actorId ??= Auth::id();

        // Serialise writers per organization so the hash chain cannot fork.
        DB::select('select pg_advisory_xact_lock(hashtext(?))', [$org]);
        $last = DB::selectOne('select row_hash from audit_logs where organization_id = ? order by id desc limit 1', [$org]);
        $prev = $last?->row_hash;

        $json = json_encode(self::redact($metadata), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $occurredAt = now()->toIso8601String();
        $hash = hash('sha256', implode('|', [$prev, $org, $actorType, $actorId, $action, $entityType, $entityId, $json, $occurredAt]));

        DB::insert(
            'insert into audit_logs (organization_id, actor_type, actor_id, action, entity_type, entity_id,
                                     metadata_redacted_json, prev_hash, row_hash, occurred_at)
             values (?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?)',
            [$org, $actorType, $actorId, $action, $entityType, $entityId, $json, $prev, $hash, $occurredAt]
        );
    }

    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}
