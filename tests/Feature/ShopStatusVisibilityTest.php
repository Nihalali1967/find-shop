<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Shops\ShopService;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopStatusVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);

        $owner = User::factory()->create();

        $this->shop = Shop::create([
            'owner_id' => $owner->id,
            'name' => 'Vertex Surfaces',
            'name_key' => 'vertex surfaces',
            'slug' => 'vertex-surfaces',
            'locality' => 'Kochi',
            'pincode' => '682001',
            'address' => 'Marine Drive',
            'status' => Shop::STATUS_ACTIVE,
        ]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'subcategory_id' => Subcategory::first()->id,
            'unit_id' => Unit::where('code', 'meter')->value('id'),
            'name' => 'Vertex marble',
            'title' => 'Vertex marble for flooring',
            'description' => 'Listing',
            'unit_count' => 1,
            'price_paise' => 120000,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductImage::create(['product_id' => $this->product->id, 'path' => 'products/v.jpg', 'sort_order' => 1]);
    }

    public function test_an_active_shop_is_publicly_visible(): void
    {
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/products/{$this->product->id}")->assertOk();
    }

    public function test_suspending_a_shop_hides_its_products_immediately(): void
    {
        app(ShopService::class)->changeStatus($this->shop, Shop::STATUS_SUSPENDED, 'Policy breach');

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/products/{$this->product->id}")->assertNotFound();
        $this->getJson("/api/v1/shops/{$this->shop->id}")->assertNotFound();
    }

    public function test_suspending_revokes_existing_api_tokens(): void
    {
        $token = $this->shop->owner->createToken('api', ['shop'])->plainTextToken;

        app(ShopService::class)->changeStatus($this->shop, Shop::STATUS_SUSPENDED, 'Policy breach');

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_suspended_shop_cannot_write_even_with_a_fresh_token(): void
    {
        app(ShopService::class)->changeStatus($this->shop, Shop::STATUS_SUSPENDED, 'Policy breach');

        $token = $this->shop->owner->createToken('api', ['shop'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/shop/products', [])
            ->assertStatus(403)
            ->assertJsonPath('code', 'SHOP_SUSPENDED');
    }

    public function test_web_shop_routes_redirect_restricted_owners_to_the_status_screen(): void
    {
        app(ShopService::class)->changeStatus($this->shop, Shop::STATUS_INACTIVE, 'Paperwork pending');

        $this->actingAs($this->shop->owner)
            ->get('/shop')
            ->assertRedirect(route('shop.status'));

        $this->actingAs($this->shop->owner)
            ->get('/shop/status')
            ->assertOk()
            ->assertSee('Your shop account is inactive', false);
    }

    public function test_deactivating_a_category_hides_its_products(): void
    {
        $subcategory = $this->product->subcategory;
        $subcategory->category->forceFill(['status' => 'inactive'])->save();

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/products/{$this->product->id}")->assertNotFound();
    }
}
