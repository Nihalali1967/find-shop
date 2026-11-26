<?php

namespace App\Services\Sms;

/**
 * A gateway whose provider generates and checks the code itself (e.g. Twilio Verify),
 * so the application never sees the code and cannot verify it against a local hash.
 */
interface VerifiesOtpRemotely
{
    /**
     * Ask the provider whether the code is correct for the phone's pending verification.
     */
    public function checkOtp(string $phone, string $code): bool;
}
