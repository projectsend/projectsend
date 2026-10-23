<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The AI assistant connector (MCP)
|--------------------------------------------------------------------------
|
| The MCP server an assistant connects to at /mcp. Everything here is a
| number somebody may want to move after watching real traffic, or a
| fact about this installation the server has to state to the assistant.
|
| The server itself is gated by the `ai.connector` capability, which is
| Cloud-only, so nothing in this file switches it on where it does not
| belong: a community installation has no capability and no route that
| answers.
|
*/

return [

    // How much text a single call may carry as `content`. Above this the
    // assistant is told to open a resumable upload instead. One megabyte
    // is the plan's decision 6: enough for any document an assistant
    // writes in a conversation, small enough that a JSON-RPC body never
    // becomes the slow path.
    'inline_content_max_bytes' => (int) env('PROJECTSEND_MCP_INLINE_MAX_BYTES', 1024 * 1024),

    // How long an idempotency key remembers its answer. A day: any retry
    // of a timed-out call falls inside it, and a repeat a day later is a
    // new request rather than a retry.
    'idempotency_ttl_seconds' => (int) env('PROJECTSEND_MCP_IDEMPOTENCY_TTL', 86400),

    // Calls per minute per token. The plan (decision 14) says the real
    // number comes from measuring; this is the starting point, in the
    // order of what the API already allows per token.
    'calls_per_minute' => (int) env('PROJECTSEND_MCP_CALLS_PER_MINUTE', 120),

    // Where "how to connect each assistant" is documented. The page is
    // drafted on the marketing site and published with the server.
    'docs_url' => env('PROJECTSEND_MCP_DOCS_URL', 'https://projectsend.cloud/docs/connect-your-ai-assistant'),

];
