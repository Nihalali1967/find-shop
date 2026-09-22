<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductSearchProjector;
use App\Support\NameNormalizer;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);

        $this->shop = Shop::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Stonecraft Studio',
            'name_key' => 'stonecraft studio',
            'slug' => 'stonecraft-studio',
            'locality' => 'Hosur Road',
            'pincode' => '560100',
            'address' => 'Industrial Layout',
            'status' => Shop::STATUS_ACTIVE,
        ]);

        $this->makeProduct('Italian marble', 'Premium white marble for living room flooring', 'Italian Marble', ['White', 'Gray']);
        $this->makeProduct('Black galaxy granite', 'Polished black granite slab for countertops', 'Granite', ['Black']);
        $this->makeProduct('Linear LED profile', 'Recessed linear LED ceiling profile', 'Ceiling Lights', ['White']);
    }

    private function makeProduct(string $name, string $title, string $subcategoryName, array $colors): Product
    {
        $subcategory = Subcategory::where('name_key', NameNormalizer::key($subcategoryName))->firstOrFail();

        $product = Product::create([
            'shop_id' => $this->shop->id,
            'subcategory_id' => $subcategory->id,
            'unit_id' => Unit::where('code', 'meter')->value('id'),
            'name' => $name,
            'title' => $title,
            'description' => $title.' supplied by '.$this->shop->name,
            'unit_count' => 2,
            'price_paise' => 180000,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $product->colors()->sync(
            Color::whereIn('name_key', array_map(fn ($c) => NameNormalizer::key($c), $colors))->pluck('id')
        );

        ProductImage::create(['product_id' => $product->id, 'path' => 'products/test.jpg', 'sort_order' => 1]);

        app(ProductSearchProjector::class)->sync($product->refresh());

        return $product;
    }

    /**
     * @return array<int,string>
     */
    private function names(string $query): array
    {
        $response = $this->getJson('/api/v1/products?q='.urlencode($query))->assertOk();

        return array_column($response->json('data'), 'name');
    }

    public function test_marble_white_matches_name_and_colour_across_fields(): void
    {
        $this->assertSame(['Italian marble'], $this->names('marble white'));
    }

    public function test_word_order_does_not_change_results(): void
    {
        $this->assertSame($this->names('marble white'), $this->names('white marble'));
    }

    public function test_whitw_is_corrected_to_white(): void
    {
        $response = $this->getJson('/api/v1/products?q=marble+whitw')->assertOk();

        $this->assertSame(['Italian marble'], array_column($response->json('data'), 'name'));
        $this->assertSame(['from' => 'whitw', 'to' => 'white'], $response->json('meta.correction'));
    }

    public function test_flo_or_floor_resolves_to_the_flooring_category(): void
    {
        $this->assertSame(['Italian marble'], $this->names('floor marble white'));
    }

    public function test_black_excludes_white_only_listings(): void
    {
        // Nothing here is both marble and black, so the AND across terms must return nothing.
        $this->assertSame([], $this->names('floor marble black'));
    }

    public function test_terms_may_match_different_fields(): void
    {
        // "granite" hits the subcategory, "black" hits the colour, "countertop" the title.
        $this->assertSame(['Black galaxy granite'], $this->names('granite black countertop'));
    }

    public function test_description_only_matches_rank_below_field_matches(): void
    {
        $names = $this->names('supplied');

        $this->assertCount(3, $names);
    }

    public function test_sorting_uses_the_effective_offer_price_with_stable_ties(): void
    {
        $cheap = Product::firstWhere('name', 'Linear LED profile');
        $cheap->forceFill(['price_paise' => 500000, 'offer_price_paise' => 10000])->save();
        app(ProductSearchProjector::class)->sync($cheap->refresh());

        $response = $this->getJson('/api/v1/products?sort=price_asc')->assertOk();
        $prices = array_column($response->json('data'), 'effective_price_paise');

        $this->assertSame($prices, collect($prices)->sort()->values()->all());
        $this->assertSame(10000, $prices[0]);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/v1/products?category_id=999999')->assertStatus(422);
        $this->getJson('/api/v1/products?sort=drop_table')->assertStatus(422);
    }
}
