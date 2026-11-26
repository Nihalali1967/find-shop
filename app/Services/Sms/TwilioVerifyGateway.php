<?php

namespace App\Services\Sms;

use App\Exceptions\MarketplaceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio Verify: Twilio generates, sends and checks the code.
 *
 * @see https://www.twilio.com/docs/verify/api
 */
class TwilioVerifyGateway implements SmsGateway, VerifiesOtpRemotely
{
    private const BASE_URL = 'https://verify.twilio.com/v2/Services/';

    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $serviceSid,
    ) {}

    /**
     * The $code argument is ignored: Twilio generates its own code.
     */
    public function sendOtp(string $phone, string $code, string $purpose): bool
    {
        $response = $this->call('Verifications', ['To' => $phone, 'Channel' => 'sms']);

        if ($response->successful()) {
            return true;
        }

        $error = (int) $response->json('code');

        Log::warning('sms.twilio.send_failed', [
            'status' => $response->status(),
            'twilio_code' => $error,
            'message' => $response->json('message'),
        ]);

        throw match ($error) {
            60200, 21211 => MarketplaceException::invalid('Enter a valid phone number with country code.', 'PHONE_INVALID'),
            60203 => MarketplaceException::otpThrottled(600, 'Too many codes sent to this number. Try again in 10 minutes.'),
            60205, 60410, 60605 => MarketplaceException::invalid('We cannot send a code to this number.', 'PHONE_UNSUPPORTED'),
            default => $this->sendFailed(),
        };
    }

    public function checkOtp(string $phone, string $code): bool
    {
        $response = $this->call('VerificationCheck', ['To' => $phone, 'Code' => $code]);

        // 404 = no pending verification (expired, already approved, or too many checks).
        if (! $response->successful()) {
            if ($response->status() !== 404) {
                Log::warning('sms.twilio.check_failed', [
                    'status' => $response->status(),
                    'twilio_code' => $response->json('code'),
                    'message' => $response->json('message'),
                ]);
            }

            return false;
        }

        return $response->json('status') === 'approved';
    }

    public function name(): string
    {
        return 'twilio';
    }

    private function call(string $endpoint, array $form): Response
    {
        try {
            return $this->client()->post(self::BASE_URL.$this->serviceSid.'/'.$endpoint, $form);
        } catch (ConnectionException $e) {
            Log::error('sms.twilio.unreachable', ['error' => $e->getMessage()]);

            throw $this->sendFailed();
        }
    }

    private function client(): PendingRequest
    {
        return Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->acceptJson()
            ->timeout(10);
    }

    private function sendFailed(): MarketplaceException
    {
        return new MarketplaceException('OTP_SEND_FAILED', 'We could not send the code right now. Please try again.', 503);
    }
}
