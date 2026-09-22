<?php

namespace Tests\Feature;

use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
    }

    public function test_the_client_catalog_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Browse materials', false);
    }

    public function test_the_public_assets_are_served_without_a_build_step(): void
    {
        $this->assertFileExists(public_path('assets/marketplace.css'));
        $this->assertFileExists(public_path('assets/marketplace.js'));
        $this->assertStringNotContainsString('@vite', file_get_contents(resource_path('views/layouts/public.blade.php')));
    }

    public function test_the_login_screens_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/shop/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_routes_require_their_own_guard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_shop_routes_require_a_session(): void
    {
        $this->get('/shop')->assertRedirect(route('shop.login'));
    }
}
