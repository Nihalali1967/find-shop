<?php

namespace Tests\Feature;

use App\Exceptions\MarketplaceException;
use App\Models\OtpChallenge;
use App\Services\Otp\OtpService;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioVerifyOtpTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://verify.twilio.com/v2/Services/VAtest/Verifications';

    private const CHECK_URL = 'https://verify.twilio.com/v2/Services/VAtest/VerificationCheck';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.driver' => 'twilio',
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'secret',
            'services.twilio.verify_sid' => 'VAtest',
        ]);

        $this->app->forgetInstance(SmsGateway::class);
    }

    private function service(): OtpService
    {
        return app(OtpService::class);
    }

    public function test_requesting_a_code_starts_a_twilio_verification(): void
    {
        Http::fake([self::SEND_URL => Http::response(['status' => 'pending'], 201)]);

        $challenge = $this->service()->request('9800000020', 'client_login', '127.0.0.1', 'phpunit');

        $this->assertSame('+919800000020', $challenge->phone);

        Http::assertSent(fn (Request $request) => $request->url() === self::SEND_URL
            && $request['To'] === '+919800000020'
            && $request['Channel'] === 'sms'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('ACtest:secret')));
    }

    public function test_an_approved_check_consumes_the_challenge(): void
    {
        Http::fake([
            self::SEND_URL => Http::response(['status' => 'pending'], 201),
            self::CHECK_URL => Http::response(['status' => 'approved'], 200),
        ]);

        $otp = $this->service();
        $challenge = $otp->request('+919800000021', 'client_login');

        $verified = $otp->verify($challenge->challenge_id, '482913', 'client_login');

        $this->assertNotNull($verified->consumed_at);

        Http::assertSent(fn (Request $request) => $request->url() === self::CHECK_URL
            && $request['To'] === '+919800000021'
            && $request['Code'] === '482913');
    }

    public function test_a_wrong_code_is_rejected_and_counted(): void
    {
        Http::fake([
            self::SEND_URL => Http::response(['status' => 'pending'], 201),
            self::CHECK_URL => Http::response(['status' => 'pending'], 200),
        ]);

        $otp = $this->service();
        $challenge = $otp->request('+919800000022', 'client_login');

        try {
            $otp->verify($challenge->challenge_id, '000000', 'client_login');
            $this->fail('A wrong code must be rejected.');
        } catch (MarketplaceException $e) {
            $this->assertSame('OTP_INVALID', $e->errorCode);
        }

        $this->assertSame(1, $challenge->fresh()->attempts);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_an_expired_twilio_verification_is_rejected(): void
    {
        Http::fake([
            self::SEND_URL => Http::response(['status' => 'pending'], 201),
            self::CHECK_URL => Http::response(['code' => 20404, 'message' => 'Not found'], 404),
        ]);

        $otp = $this->service();
        $challenge = $otp->request('+919800000023', 'client_login');

        $this->expectException(MarketplaceException::class);
        $otp->verify($challenge->challenge_id, '123456', 'client_login');
    }

    public function test_a_failed_send_leaves_no_challenge_behind(): void
    {
        Http::fake([self::SEND_URL => Http::response(['code' => 60200, 'message' => 'Invalid parameter'], 400)]);

        try {
            $this->service()->request('+1234', 'client_login');
            $this->fail('An invalid number must be rejected.');
        } catch (MarketplaceException $e) {
            $this->assertSame('PHONE_INVALID', $e->errorCode);
        }

        $this->assertSame(0, OtpChallenge::count());
    }

    public function test_provider_outage_returns_a_retryable_error(): void
    {
        Http::fake([self::SEND_URL => Http::response(['code' => 20500, 'message' => 'Internal error'], 500)]);

        try {
            $this->service()->request('+919800000024', 'client_login');
            $this->fail('A provider error must surface.');
        } catch (MarketplaceException $e) {
            $this->assertSame('OTP_SEND_FAILED', $e->errorCode);
            $this->assertSame(503, $e->status);
        }
    }
}
