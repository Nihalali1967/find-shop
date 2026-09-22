<?php

namespace App\Exceptions;

use RuntimeException;

class MarketplaceException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidOtp(string $message = 'The code is invalid or has expired.', int $status = 422): self
    {
        return new self('OTP_INVALID', $message, $status);
    }

    public static function otpThrottled(int $retryAfter, string $message = 'Please wait before requesting another code.'): self
    {
        return new self('OTP_THROTTLED', $message, 429, ['retry_after' => $retryAfter]);
    }

    public static function shopInactive(string $message, string $code = 'SHOP_INACTIVE'): self
    {
        return new self($code, $message, 403);
    }

    public static function conflict(string $message, string $code = 'CONFLICT'): self
    {
        return new self($code, $message, 409);
    }

    public static function invalid(string $message, string $code = 'INVALID'): self
    {
        return new self($code, $message, 422);
    }
}
