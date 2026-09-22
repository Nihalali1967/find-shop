<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function sendOtp(string $phone, string $code, string $purpose): bool
    {
        // Never log the raw code in production; this gateway is for local/staging only.
        Log::info('sms.otp.dispatched', [
            'phone' => $this->mask($phone),
            'purpose' => $purpose,
        ]);

        return true;
    }

    public function name(): string
    {
        return 'log';
    }

    protected function mask(string $phone): string
    {
        return substr($phone, 0, 3).str_repeat('*', max(0, strlen($phone) - 5)).substr($phone, -2);
    }
}
