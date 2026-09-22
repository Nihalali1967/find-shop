<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'challenge_id', 'phone', 'purpose', 'code_hash', 'attempts', 'max_attempts',
    'ip_address', 'user_agent', 'expires_at', 'resend_available_at',
])]
#[Hidden(['code_hash'])]
class OtpChallenge extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'resend_available_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed() && ! $this->isExhausted();
    }

    public function resendSecondsRemaining(): int
    {
        if (! $this->resend_available_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->resend_available_at, false));
    }
}
