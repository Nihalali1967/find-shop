<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shopA;

    private Shop $shopB;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);

        $this->shopA = $this->makeShop('Alpha Surfaces', 'alpha@example.com');
        $this->shopB = $this->makeShop('Beta Surfaces', 'beta@example.com');

        $this->productA = $this->makeProduct($this->shopA, 'Alpha marble');
        $this->productB = $this->makeProduct($this->shopB, 'Beta marble');
    }

    private function makeShop(string $name, string $email): Shop
    {
        $owner = User::factory()->create(['email' => $email]);

        return Shop::create([
            'owner_id' => $owner->id,
            'name' => $name,
            'name_key' => strtolower($name),
            'slug' => str_replace(' ', '-', strtolower($name)),
            'locality' => 'Pune',
            'pincode' => '411001',
            'address' => 'Main Road',
            'email' => $email,
            'status' => Shop::STATUS_ACTIVE,
        ]);
    }

    private function makeProduct(Shop $shop, string $name): Product
    {
        $product = Product::create([
            'shop_id' => $shop->id,
            'subcategory_id' => Subcategory::first()->id,
            'unit_id' => Unit::where('code', 'meter')->value('id'),
            'name' => $name,
            'title' => $name.' for interiors',
            'description' => 'Test listing',
            'unit_count' => 1,
            'price_paise' => 100000,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x.jpg', 'sort_order' => 1]);

        return $product;
    }

    public function test_web_edit_of_another_shops_product_is_forbidden(): void
    {
        $this->actingAs($this->shopB->owner)
            ->get("/shop/products/{$this->productA->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->shopB->owner)
            ->patch("/shop/products/{$this->productA->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Alpha marble', $this->productA->fresh()->name);
    }

    public function test_web_delete_of_another_shops_product_is_forbidden(): void
    {
        $this->actingAs($this->shopB->owner)
            ->delete("/shop/products/{$this->productA->id}")
            ->assertForbidden();

        $this->assertNull($this->productA->fresh()->deleted_at);
    }

    public function test_api_mutations_of_another_shops_product_are_forbidden(): void
    {
        $token = $this->shopB->owner->createToken('api', ['shop'])->plainTextToken;

        $this->withApiToken($token)
            ->patchJson("/api/v1/shop/products/{$this->productA->id}", [])
            ->assertForbidden();

        $this->withApiToken($token)
            ->deleteJson("/api/v1/shop/products/{$this->productA->id}")
            ->assertForbidden();
    }

    public function test_a_shop_cannot_delete_an_image_through_a_guessed_id(): void
    {
        $foreignImage = $this->productA->images()->firstOrFail();

        $this->actingAs($this->shopB->owner)
            ->delete("/shop/products/{$this->productB->id}/images/{$foreignImage->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('product_images', ['id' => $foreignImage->id]);
    }

    public function test_the_shop_list_only_contains_own_products(): void
    {
        $token = $this->shopA->owner->createToken('api', ['shop'])->plainTextToken;

        $names = array_column(
            $this->withApiToken($token)->getJson('/api/v1/shop/products')->assertOk()->json('data'),
            'name'
        );

        $this->assertSame(['Alpha marble'], $names);
    }
}
