<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        if (app()->environment('production') && $password === 'password') {
            $this->command?->warn('Skipped admin seeding: set ADMIN_PASSWORD before seeding production.');

            return;
        }

        Admin::updateOrCreate(
            ['email' => $email],
            ['name' => 'Administrator', 'password' => $password, 'role' => 'admin', 'is_active' => true],
        );

        $this->command?->info("Admin ready: {$email}");
    }
}
