<?php

namespace App\Http\Middleware;

use App\Mcp\McpAuditLogger;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zugriffsschutz der MCP-Route nach `auth:api`: Es muss ein Passport-Access-Token mit Benutzer, Scope `mcp:use`
 * und verifizierter E-Mail-Adresse vorliegen (fail-closed). Tokens ohne Benutzer (Client Credentials) oder
 * Cookie-Tokens werden wie ein ungueltiges Token behandelt (401), ohne Unterschied in der Antwort.
 */
class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->token();

        if ($user === null || ! $token instanceof AccessToken) {
            throw new AuthenticationException;
        }

        $clientId = is_string($token->oauth_client_id ?? null) ? $token->oauth_client_id : null;

        if (! $user->hasVerifiedEmail()) {
            McpAuditLogger::record(McpAuditLogger::EVENT_ACCESS_DENIED, 'unverified', $user->getAuthIdentifier(), $clientId);

            return response()->json(['error' => 'email_not_verified'], 403);
        }

        if (! in_array(Registrar::OAUTH_SCOPE, $token->oauth_scopes ?? [], true)) {
            McpAuditLogger::record(McpAuditLogger::EVENT_ACCESS_DENIED, 'missing_scope', $user->getAuthIdentifier(), $clientId);

            return response()->json(['error' => 'insufficient_scope'], 403);
        }

        return $next($request);
    }
}
