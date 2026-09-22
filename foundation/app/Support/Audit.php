<?php

namespace App\Support;

use App\Models\AuditLog;

class Audit
{
    public static function record(string $event, ?int $actor = null, ?int $subject = null, array $metadata = [], string $source = 'web'): void
    {
        // Explicit allowlist: never persist request bodies, credentials, tokens, emails or raw responses.
        AuditLog::create([
            'event' => $event,
            'actor_id' => $actor,
            'subject_id' => $subject,
            'source' => $source,
            'metadata' => array_intersect_key($metadata, array_flip(['from_role', 'to_role', 'entity_type', 'entity_id', 'changed_fields', 'from_status', 'to_status', 'image_id', 'quantity', 'from_stock', 'to_stock'])),
            'created_at' => now(),
        ]);
    }
}
