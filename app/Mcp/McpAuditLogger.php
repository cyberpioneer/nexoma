<?php

namespace App\Mcp;

use Illuminate\Support\Facades\Log;

/**
 * Schreibt Audit-Eintraege der MCP-Schnittstelle in den Channel `mcp_audit`.
 *
 * Erlaubt sind ausschliesslich die Felder von {@see self::FIELDS}. Nie protokolliert werden Suchtexte, Referenznummern,
 * Tokens, Authorization-Header, IP-Adressen, User-Agents oder E-Mail-Adressen.
 */
final class McpAuditLogger
{
    /** @var list<string> */
    public const FIELDS = ['event', 'user_id', 'client_id', 'tool', 'status', 'duration_ms'];

    public const EVENT_TOOL_CALL = 'tool_call';

    public const EVENT_ACCESS_DENIED = 'access_denied';

    /**
     * @param  'tool_call'|'access_denied'  $event
     * @param  'ok'|'invalid_input'|'upstream_unavailable'|'error'|'unverified'|'missing_scope'  $status
     */
    public static function record(string $event, string $status, int|string|null $userId, ?string $clientId, ?string $tool = null, ?int $durationMs = null): void
    {
        Log::channel('mcp_audit')->info('mcp_audit', [
            'event' => $event,
            'user_id' => $userId,
            'client_id' => $clientId,
            'tool' => $tool,
            'status' => $status,
            'duration_ms' => $durationMs,
        ]);
    }
}
