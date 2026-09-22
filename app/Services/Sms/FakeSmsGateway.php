<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;

class FakeSmsGateway implements SmsGateway
{
    public static string $cacheKey = 'fake_sms:last_otp';

    public function sendOtp(string $phone, string $code, string $purpose): bool
    {
        Cache::put(self::$cacheKey, ['phone' => $phone, 'code' => $code, 'purpose' => $purpose], now()->addMinutes(10));

        return true;
    }

    public function name(): string
    {
        return 'fake';
    }

    public static function last(): ?array
    {
        return Cache::get(self::$cacheKey);
    }
}
