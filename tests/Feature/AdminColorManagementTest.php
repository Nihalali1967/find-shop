<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductSearchDocument;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductSearchProjector;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminColorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);

        $this->admin = Admin::factory()->create();
    }

    /*
    |----------------------------------------------------------------------
    | Web (admin session)
    |----------------------------------------------------------------------
    */

    public function test_the_colors_screen_lists_the_seeded_palette(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.colors.index'))
            ->assertOk()
            ->assertSee('White')
            ->assertSee('Multicolor')
            ->assertSee('Add color');
    }

    public function test_an_admin_can_create_a_color(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.colors.store'), [
                'name' => 'Teal',
                'hex' => '#0f766e',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('colors', [
            'name' => 'Teal',
            'name_key' => 'teal',
            'hex' => '#0f766e',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_color_names_are_rejected_regardless_of_case(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.colors.store'), [
                'name' => 'WHITE',
                'hex' => '#ffffff',
            ])
            ->assertInvalid('name');

        $this->assertSame(16, Color::count());
    }

    public function test_a_new_color_needs_a_hex_or_the_multicolor_flag(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.colors.store'), ['name' => 'Mystery'])
            ->assertInvalid('hex');
    }

    public function test_an_admin_can_edit_a_color_inline(): void
    {
        $color = Color::where('name_key', 'white')->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.colors.update', $color), [
                'name' => 'Snow White',
                'hex' => '#fafafa',
                'is_active' => '1',
                'sort_order' => $color->sort_order,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('colors', [
            'id' => $color->id,
            'name' => 'Snow White',
            'name_key' => 'snow white',
            'hex' => '#fafafa',
        ]);
    }

    public function test_deactivating_a_color_hides_it_from_the_public_palette(): void
    {
        $color = Color::where('name_key', 'white')->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.colors.update', $color), [
                'name' => $color->name,
                'hex' => $color->hex,
                'is_active' => '0',
                'sort_order' => $color->sort_order,
            ])
            ->assertRedirect();

        $this->getJson('/api/v1/colors')
            ->assertOk()
            ->assertJsonMissing(['name' => 'White']);
    }

    public function test_a_color_in_use_cannot_be_deleted_until_detached(): void
    {
        $color = Color::where('name_key', 'white')->firstOrFail();
        $product = $this->createProduct();
        $product->colors()->sync([$color->id]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.colors.destroy', $color))
            ->assertInvalid('color');

        $this->assertDatabaseHas('colors', ['id' => $color->id]);

        $product->colors()->detach();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.colors.destroy', $color))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('colors', ['id' => $color->id]);
    }

    public function test_non_admins_cannot_use_the_web_color_routes(): void
    {
        $this->get(route('admin.colors.index'))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.colors.index'))
            ->assertRedirect(route('admin.login'));
    }

    /*
    |----------------------------------------------------------------------
    | API (Sanctum + admin ability)
    |----------------------------------------------------------------------
    */

    public function test_the_admin_color_api_supports_full_crud(): void
    {
        $token = $this->admin->createToken('api', ['admin'])->plainTextToken;

        $this->withApiToken($token)
            ->getJson('/api/v1/admin/colors')
            ->assertOk()
            ->assertJsonCount(16, 'data')
            ->assertJsonPath('data.0.name', 'White');

        $created = $this->withApiToken($token)
            ->postJson('/api/v1/admin/colors', [
                'name' => 'Teal',
                'hex' => '#0f766e',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Teal')
            ->assertJsonPath('data.hex', '#0f766e')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.products_count', 0);

        $id = $created->json('data.id');

        $this->withApiToken($token)
            ->patchJson("/api/v1/admin/colors/{$id}", [
                'name' => 'Deep Teal',
                'hex' => '#0e7490',
                'is_active' => false,
                'sort_order' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Deep Teal')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 3);

        $this->assertDatabaseHas('colors', ['id' => $id, 'name_key' => 'deep teal']);

        // The inactive color is excluded from the default admin list...
        $this->withApiToken($token)
            ->getJson('/api/v1/admin/colors')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Deep Teal']);

        // ...but visible with include_inactive.
        $this->withApiToken($token)
            ->getJson('/api/v1/admin/colors?include_inactive=1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Deep Teal', 'is_active' => false]);

        $this->withApiToken($token)
            ->deleteJson("/api/v1/admin/colors/{$id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('colors', ['id' => $id]);
    }

    public function test_the_admin_color_api_accepts_multicolor_swatch_definitions(): void
    {
        $token = $this->admin->createToken('api', ['admin'])->plainTextToken;

        $this->withApiToken($token)
            ->postJson('/api/v1/admin/colors', [
                'name' => 'Mixed Tiles',
                'is_multicolor' => true,
                'hex' => '#123456',
            ])
            ->assertCreated()
            ->assertJsonPath('data.hex', null)
            ->assertJsonPath('data.is_multicolor', true);

        $this->assertDatabaseHas('colors', [
            'name_key' => 'mixed tiles',
            'hex' => null,
            'swatch_class' => 'swatch-multicolor',
        ]);
    }

    public function test_the_admin_color_api_rejects_duplicate_names_and_invalid_hex(): void
    {
        $token = $this->admin->createToken('api', ['admin'])->plainTextToken;

        $this->withApiToken($token)
            ->postJson('/api/v1/admin/colors', ['name' => ' gray ', 'hex' => '#000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->withApiToken($token)
            ->postJson('/api/v1/admin/colors', ['name' => 'Neon', 'hex' => 'neon-green'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('hex');
    }

    public function test_a_color_attached_to_products_cannot_be_deleted_through_the_api(): void
    {
        $color = Color::where('name_key', 'blue')->firstOrFail();
        $product = $this->createProduct();
        $product->colors()->sync([$color->id]);

        $token = $this->admin->createToken('api', ['admin'])->plainTextToken;

        $this->withApiToken($token)
            ->deleteJson("/api/v1/admin/colors/{$color->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'COLOR_IN_USE')
            ->assertJsonPath('products_count', 1);

        $this->assertDatabaseHas('colors', ['id' => $color->id]);
    }

    public function test_the_admin_color_api_requires_an_admin_token(): void
    {
        $this->getJson('/api/v1/admin/colors')->assertUnauthorized();

        $customerToken = User::factory()->create()->createToken('api', ['shop'])->plainTextToken;

        $this->withApiToken($customerToken)
            ->postJson('/api/v1/admin/colors', ['name' => 'Teal'])
            ->assertForbidden();
    }

    public function test_renaming_a_color_refreshes_product_search_documents(): void
    {
        $color = Color::where('name_key', 'white')->firstOrFail();
        $product = $this->createProduct();
        $product->colors()->sync([$color->id]);

        app(ProductSearchProjector::class)->sync($product);

        $before = ProductSearchDocument::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('white', $before->field_map['colors']);

        $token = $this->admin->createToken('api', ['admin'])->plainTextToken;

        $this->withApiToken($token)
            ->patchJson("/api/v1/admin/colors/{$color->id}", [
                'name' => 'Snow White',
                'hex' => $color->hex,
                'is_active' => true,
                'sort_order' => $color->sort_order,
            ])
            ->assertOk();

        $after = ProductSearchDocument::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('snow white', $after->field_map['colors']);
    }

    /*
    |----------------------------------------------------------------------
    | Helpers
    |----------------------------------------------------------------------
    */

    private function createProduct(): Product
    {
        $owner = User::factory()->create();

        $shop = Shop::create([
            'owner_id' => $owner->id,
            'name' => 'Palette Studios',
            'name_key' => 'palette studios',
            'slug' => 'palette-studios',
            'locality' => 'Kochi',
            'pincode' => '682001',
            'address' => 'Marine Drive',
            'status' => Shop::STATUS_ACTIVE,
        ]);

        return Product::create([
            'shop_id' => $shop->id,
            'subcategory_id' => Subcategory::first()->id,
            'unit_id' => Unit::where('code', 'meter')->value('id'),
            'name' => 'Palette marble',
            'title' => 'Palette marble for flooring',
            'description' => 'Listing used to exercise color ownership.',
            'unit_count' => 1,
            'price_paise' => 120000,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
