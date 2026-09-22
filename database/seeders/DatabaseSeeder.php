<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxonomySeeder::class,
            AdminSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
