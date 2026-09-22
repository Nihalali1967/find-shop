<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * Deliver a one-time code.
     *
     * @return bool whether the provider accepted the message
     */
    public function sendOtp(string $phone, string $code, string $purpose): bool;

    /**
     * Driver name for diagnostics.
     */
    public function name(): string;
}
