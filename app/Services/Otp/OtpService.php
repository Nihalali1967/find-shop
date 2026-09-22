<?php

namespace App\Services\Otp;

use App\Exceptions\MarketplaceException;
use App\Models\OtpChallenge;
use App\Services\Sms\SmsGateway;
use App\Support\NameNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OtpService
{
    public function __construct(private readonly SmsGateway $gateway) {}

    /**
     * Issue a fresh challenge for a phone/purpose pair and dispatch the code.
     */
    public function request(string $phone, string $purpose, ?string $ip = null, ?string $userAgent = null): OtpChallenge
    {
        $phone = NameNormalizer::phone($phone);
        $purpose = $this->assertPurpose($purpose);
        $settings = config('marketplace.otp');

        $phoneKey = 'otp:phone:'.$phone;
        $ipKey = 'otp:ip:'.($ip ?: 'unknown');

        if (RateLimiter::tooManyAttempts($phoneKey, $settings['max_per_phone_per_hour'])) {
            throw MarketplaceException::otpThrottled(RateLimiter::availableIn($phoneKey));
        }

        if (RateLimiter::tooManyAttempts($ipKey, $settings['max_per_ip_per_hour'])) {
            throw MarketplaceException::otpThrottled(RateLimiter::availableIn($ipKey));
        }

        if ($this->onResendCooldown($phone, $purpose)) {
            $latest = $this->latestActive($phone, $purpose);

            throw MarketplaceException::otpThrottled($latest?->resendSecondsRemaining() ?? $settings['resend_seconds']);
        }

        $code = $this->generateCode();

        $challenge = DB::transaction(function () use ($phone, $purpose, $code, $ip, $userAgent, $settings) {
            // Invalidate any still-active challenge for this phone/purpose.
            OtpChallenge::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return OtpChallenge::create([
                'challenge_id' => (string) Str::uuid(),
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'max_attempts' => $settings['max_attempts'],
                'ip_address' => $ip,
                'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
                'expires_at' => now()->addSeconds($settings['ttl_seconds']),
                'resend_available_at' => now()->addSeconds($settings['resend_seconds']),
            ]);
        });

        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $this->gateway->sendOtp($phone, $code, $purpose);

        return $challenge;
    }

    /**
     * Verify a code and consume the challenge exactly once.
     */
    public function verify(string $challengeId, string $code, string $purpose): OtpChallenge
    {
        $purpose = $this->assertPurpose($purpose);

        $result = DB::transaction(function () use ($challengeId, $code, $purpose) {
            $challenge = OtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                throw MarketplaceException::invalidOtp('This verification request is no longer valid.');
            }

            if ($challenge->isConsumed()) {
                throw MarketplaceException::invalidOtp('This code has already been used.');
            }

            if ($challenge->isExpired()) {
                throw MarketplaceException::invalidOtp('This code has expired. Request a new one.');
            }

            if ($challenge->isExhausted()) {
                throw MarketplaceException::otpThrottled(0, 'Too many attempts. Request a new code.');
            }

            if (! Hash::check($code, $challenge->code_hash)) {
                // Signal a mismatch; the attempt is recorded outside this transaction
                // so a rolled-back throw cannot erase the failed-attempt count.
                return false;
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            return $challenge;
        });

        if ($result === false) {
            OtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->where('purpose', $purpose)
                ->increment('attempts');

            throw MarketplaceException::invalidOtp();
        }

        return $result;
    }

    public function latestActive(string $phone, string $purpose): ?OtpChallenge
    {
        return OtpChallenge::query()
            ->where('phone', NameNormalizer::phone($phone))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    public function generateCode(): string
    {
        $length = (int) config('marketplace.otp.length');
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    protected function onResendCooldown(string $phone, string $purpose): bool
    {
        $latest = $this->latestActive($phone, $purpose);

        return $latest !== null && $latest->resendSecondsRemaining() > 0;
    }

    protected function assertPurpose(string $purpose): string
    {
        if (! in_array($purpose, config('marketplace.purposes'), true)) {
            throw MarketplaceException::invalid('Unsupported verification purpose.');
        }

        return $purpose;
    }
}
