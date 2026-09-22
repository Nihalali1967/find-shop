<?php

namespace Tests\Feature;

use App\Exceptions\MarketplaceException;
use App\Models\Shop;
use App\Models\User;
use App\Services\Shops\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name): array
    {
        return [
            'name' => $name,
            'locality' => 'Indiranagar',
            'pincode' => '560038',
            'address' => '100 ft Road, Bengaluru',
        ];
    }

    public function test_normalized_duplicate_names_create_only_one_shop(): void
    {
        $service = app(ShopService::class);

        $first = User::factory()->create();
        $second = User::factory()->create();

        $service->register($first, $this->payload('Stone  Craft Studio'));

        try {
            $service->register($second, $this->payload('stone craft studio'));
            $this->fail('Expected a duplicate-name conflict.');
        } catch (MarketplaceException $e) {
            $this->assertSame('SHOP_NAME_TAKEN', $e->errorCode);
            $this->assertSame(409, $e->status);
        }

        $this->assertSame(1, Shop::count());
    }

    public function test_name_availability_matches_normalization(): void
    {
        $service = app(ShopService::class);
        $service->register(User::factory()->create(), $this->payload('Lumen & Co'));

        $this->assertFalse($service->isNameAvailable('  lumen & co '));
        $this->assertTrue($service->isNameAvailable('Lumen and Co'));
    }

    public function test_a_user_cannot_own_two_shops(): void
    {
        $service = app(ShopService::class);
        $user = User::factory()->create();

        $service->register($user, $this->payload('Nordic Surfaces'));

        $this->expectException(MarketplaceException::class);
        $service->register($user, $this->payload('Something Else'));
    }

    public function test_registration_endpoint_requires_a_verified_onboarding_token(): void
    {
        $user = User::factory()->create();
        $clientToken = $user->createToken('api', ['client'])->plainTextToken;

        $this->withApiToken($clientToken)
            ->postJson('/api/v1/shop/registration', $this->payload('Unverified Shop'))
            ->assertStatus(403);

        $onboardingToken = $user->createToken('api', ['shop:onboarding'])->plainTextToken;

        $this->withApiToken($onboardingToken)
            ->postJson('/api/v1/shop/registration', $this->payload('Verified Shop'))
            ->assertCreated();

        $this->assertDatabaseHas('shops', ['name' => 'Verified Shop', 'status' => 'active']);
    }

    public function test_status_changes_are_recorded_with_reason(): void
    {
        $service = app(ShopService::class);
        $shop = $service->register(User::factory()->create(), $this->payload('Aurora Tiles'));

        $service->changeStatus($shop, Shop::STATUS_SUSPENDED, 'Repeated policy breaches');

        $this->assertDatabaseHas('shop_status_events', [
            'shop_id' => $shop->id,
            'from_status' => Shop::STATUS_ACTIVE,
            'to_status' => Shop::STATUS_SUSPENDED,
            'reason' => 'Repeated policy breaches',
        ]);
    }
}
