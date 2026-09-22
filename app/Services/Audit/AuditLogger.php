<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        ?Model $actor = null,
        array $meta = [],
        ?string $ip = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_type' => $actor ? class_basename($actor) : null,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip_address' => $ip,
        ]);
    }
}
