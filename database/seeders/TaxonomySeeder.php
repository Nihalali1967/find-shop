<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\SearchAlias;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Support\NameNormalizer;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Meter', 'code' => 'meter', 'sort_order' => 1],
            ['name' => 'Square feet', 'code' => 'sqft', 'sort_order' => 2],
            ['name' => 'Feet', 'code' => 'feet', 'sort_order' => 3],
            ['name' => 'Centimeter', 'code' => 'cm', 'sort_order' => 4],
            ['name' => 'Liter', 'code' => 'liter', 'sort_order' => 5],
            ['name' => 'Piece', 'code' => 'piece', 'sort_order' => 6],
            ['name' => 'Kilogram', 'code' => 'kg', 'sort_order' => 7],
            ['name' => 'Others', 'code' => 'others', 'requires_custom' => true, 'sort_order' => 99],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(['code' => $unit['code']], $unit + ['is_active' => true]);
        }

        $colors = [
            ['name' => 'White', 'hex' => '#ffffff'],
            ['name' => 'Black', 'hex' => '#111111'],
            ['name' => 'Gray', 'hex' => '#8e8e93'],
            ['name' => 'Beige', 'hex' => '#e7d8c9'],
            ['name' => 'Brown', 'hex' => '#8b5a2b'],
            ['name' => 'Red', 'hex' => '#d64545'],
            ['name' => 'Orange', 'hex' => '#e8883a'],
            ['name' => 'Yellow', 'hex' => '#e9c46a'],
            ['name' => 'Green', 'hex' => '#4c7a5a'],
            ['name' => 'Blue', 'hex' => '#3d6fb4'],
            ['name' => 'Purple', 'hex' => '#7b5ea7'],
            ['name' => 'Pink', 'hex' => '#d98bb4'],
            ['name' => 'Gold', 'hex' => '#c9a227'],
            ['name' => 'Silver', 'hex' => '#c0c4cc'],
            ['name' => 'Transparent', 'hex' => null],
            ['name' => 'Multicolor', 'hex' => null, 'is_multicolor' => true],
        ];

        foreach ($colors as $index => $color) {
            Color::updateOrCreate(
                ['name_key' => NameNormalizer::key($color['name'])],
                [
                    'name' => $color['name'],
                    'hex' => $color['hex'] ?? null,
                    'is_multicolor' => $color['is_multicolor'] ?? false,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $taxonomy = [
            'Flooring' => ['Italian Marble', 'Granite', 'Vitrified Tiles', 'Wooden Flooring', 'Concrete'],
            'Wall & Cladding' => ['Wall Tiles', 'Stone Cladding', 'Wall Panels', 'Plaster'],
            'Sanitary Ware' => ['Wash Basins', 'Water Closets', 'Faucets', 'Showers'],
            'Doors & Windows' => ['Wooden Doors', 'UPVC Windows', 'Aluminium Sections', 'Glass Panels'],
            'Lighting' => ['Ceiling Lights', 'Wall Lights', 'Outdoor Lights', 'Profiles'],
            'Paints & Finishes' => ['Interior Paint', 'Exterior Paint', 'Texture Finish', 'Primer'],
            'Hardware' => ['Hinges', 'Handles', 'Locks', 'Fittings'],
            'Furniture' => ['Sofas', 'Tables', 'Storage', 'Beds'],
        ];

        $order = 0;

        foreach ($taxonomy as $categoryName => $subcategories) {
            $order++;

            $category = Category::updateOrCreate(
                ['name_key' => NameNormalizer::key($categoryName)],
                [
                    'name' => $categoryName,
                    'slug' => NameNormalizer::slug($categoryName),
                    'status' => 'active',
                    'sort_order' => $order,
                ],
            );

            foreach ($subcategories as $subIndex => $name) {
                Subcategory::updateOrCreate(
                    ['category_id' => $category->id, 'name_key' => NameNormalizer::key($name)],
                    [
                        'name' => $name,
                        'slug' => NameNormalizer::slug($name),
                        'status' => 'active',
                        'sort_order' => $subIndex + 1,
                    ],
                );
            }
        }

        $aliases = [
            ['floor', 'flooring', 'alias'],
            ['flooring', 'flooring', 'canonical'],
            ['colour', 'color', 'alias'],
            ['color', 'color', 'canonical'],
            ['grey', 'gray', 'canonical'],
            ['gray', 'gray', 'canonical'],
            ['tile', 'tiles', 'alias'],
            ['tiles', 'tiles', 'canonical'],
            ['marble', 'marble', 'canonical'],
            ['basin', 'wash basins', 'alias'],
            ['sofa', 'sofas', 'alias'],
            ['paint', 'paint', 'canonical'],
            ['luminaire', 'ceiling lights', 'alias'],
            ['wc', 'water closets', 'alias'],
            ['comode', 'water closets', 'alias'],
        ];

        foreach ($aliases as $alias) {
            SearchAlias::updateOrCreate(
                ['term' => NameNormalizer::key($alias[0]), 'canonical' => NameNormalizer::key($alias[1])],
                ['type' => $alias[2], 'weight' => 5],
            );
        }
    }
}
