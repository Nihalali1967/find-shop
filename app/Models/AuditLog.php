<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_type', 'actor_id', 'action', 'subject_type',
    'subject_id', 'meta', 'ip_address',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
