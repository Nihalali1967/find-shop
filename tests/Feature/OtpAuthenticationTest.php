<?php

namespace Tests\Feature;

use App\Exceptions\MarketplaceException;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\Sms\FakeSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sms.driver' => 'fake']);
    }

    private function service(): OtpService
    {
        return app(OtpService::class);
    }

    public function test_a_code_can_only_be_consumed_once(): void
    {
        $otp = $this->service();
        $challenge = $otp->request('+919800000010', 'client_login', '127.0.0.1', 'phpunit');
        $code = FakeSmsGateway::last()['code'];

        $verified = $otp->verify($challenge->challenge_id, $code, 'client_login');
        $this->assertNotNull($verified->consumed_at);

        $this->expectException(MarketplaceException::class);
        $otp->verify($challenge->challenge_id, $code, 'client_login');
    }

    public function test_a_code_is_bound_to_its_purpose(): void
    {
        $otp = $this->service();
        $challenge = $otp->request('+919800000011', 'client_login', '127.0.0.1', 'phpunit');
        $code = FakeSmsGateway::last()['code'];

        $this->expectException(MarketplaceException::class);
        $otp->verify($challenge->challenge_id, $code, 'shop_login');
    }

    public function test_attempts_are_capped(): void
    {
        $otp = $this->service();
        $challenge = $otp->request('+919800000012', 'client_login', '127.0.0.1', 'phpunit');

        for ($i = 0; $i < 5; $i++) {
            try {
                $otp->verify($challenge->challenge_id, '000000', 'client_login');
            } catch (MarketplaceException) {
                // expected
            }
        }

        $this->assertSame(5, $challenge->fresh()->attempts);

        $this->expectException(MarketplaceException::class);
        $otp->verify($challenge->challenge_id, FakeSmsGateway::last()['code'], 'client_login');
    }

    public function test_an_unknown_challenge_is_rejected_without_leaking_details(): void
    {
        $this->expectException(MarketplaceException::class);
        $this->service()->verify('does-not-exist', '123456', 'client_login');
    }

    public function test_http_verification_returns_a_token_and_replay_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '9800000013',
            'purpose' => 'client_login',
        ])->assertOk();

        $challengeId = $response->json('data.challenge_id');
        $code = $response->json('data.dev_code');

        $this->assertNotNull($challengeId);

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId,
            'phone' => '9800000013',
            'purpose' => 'client_login',
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'abilities']]);

        $this->assertDatabaseHas('users', ['phone' => '+919800000013']);

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId,
            'phone' => '9800000013',
            'purpose' => 'client_login',
            'code' => $code,
        ])->assertStatus(422);
    }

    public function test_resending_invalidates_the_previous_code(): void
    {
        $otp = $this->service();
        $first = $otp->request('+919800000014', 'client_login', '127.0.0.1', 'phpunit');
        $firstCode = FakeSmsGateway::last()['code'];

        // Move past the resend cooldown without waiting in real time.
        $first->forceFill(['resend_available_at' => now()->subMinute()])->save();

        $otp->request('+919800000014', 'client_login', '127.0.0.1', 'phpunit');

        $this->assertNotNull($first->fresh()->consumed_at);

        $this->expectException(MarketplaceException::class);
        $otp->verify($first->challenge_id, $firstCode, 'client_login');
    }

    public function test_users_are_created_with_a_verified_phone(): void
    {
        $user = User::factory()->create(['phone' => '+919800000015']);

        $this->assertNotNull($user->phone_verified_at);
    }
}
