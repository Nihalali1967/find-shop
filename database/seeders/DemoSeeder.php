<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductSearchProjector;
use App\Support\NameNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $projector = app(ProductSearchProjector::class);

        $shops = [
            [
                'name' => 'Stonecraft Studio',
                'phone' => '+919800000001',
                'locality' => 'Hosur Road',
                'pincode' => '560100',
                'address' => '12, Industrial Layout, Hosur Road, Bengaluru',
                'tint' => [76, 122, 90],
            ],
            [
                'name' => 'Lumen & Co',
                'phone' => '+919800000002',
                'locality' => 'Andheri East',
                'pincode' => '400069',
                'address' => 'Unit 4, Chakala Industrial Estate, Mumbai',
                'tint' => [201, 162, 39],
            ],
            [
                'name' => 'Nordic Surfaces',
                'phone' => '+919800000003',
                'locality' => 'Sector 63',
                'pincode' => '201301',
                'address' => 'B-27, Phase III, Noida',
                'tint' => [61, 111, 180],
            ],
        ];

        $shopModels = [];

        foreach ($shops as $data) {
            $user = User::updateOrCreate(
                ['phone' => $data['phone']],
                ['name' => $data['name'].' Owner', 'phone_verified_at' => now()],
            );

            $shopModels[$data['name']] = Shop::updateOrCreate(
                ['owner_id' => $user->id],
                [
                    'name' => $data['name'],
                    'name_key' => NameNormalizer::key($data['name']),
                    'slug' => NameNormalizer::slug($data['name']),
                    'locality' => $data['locality'],
                    'pincode' => $data['pincode'],
                    'address' => $data['address'],
                    'status' => Shop::STATUS_ACTIVE,
                    'status_changed_at' => now(),
                ],
            );
        }

        $catalogue = [
            [
                'shop' => 'Stonecraft Studio',
                'subcategory' => 'Italian Marble',
                'name' => 'Italian marble',
                'title' => 'Premium white marble for living room flooring',
                'description' => 'Imported Italian marble slab with a soft white base and subtle grey veining. Suitable for living room flooring and feature walls. Surface is honed and sealed at the factory.',
                'unit' => 'meter',
                'unit_count' => 2,
                'price' => 180000,
                'offer' => 149900,
                'colors' => ['White', 'Gray'],
                'tint' => [235, 233, 228],
            ],
            [
                'shop' => 'Stonecraft Studio',
                'subcategory' => 'Granite',
                'name' => 'Black galaxy granite',
                'title' => 'Polished black granite slab for countertops',
                'description' => 'Deep black granite with gold speckle. Ideal for kitchen countertops and stairs. Sold per running foot in slabs of 18mm thickness.',
                'unit' => 'sqft',
                'unit_count' => 10,
                'price' => 220000,
                'offer' => null,
                'colors' => ['Black', 'Gold'],
                'tint' => [24, 24, 28],
            ],
            [
                'shop' => 'Stonecraft Studio',
                'subcategory' => 'Wooden Flooring',
                'name' => 'Oak engineered flooring',
                'title' => 'Warm oak wooden flooring plank',
                'description' => 'Engineered oak flooring with a natural matte finish. Click-lock installation, suitable for bedrooms and living areas.',
                'unit' => 'meter',
                'unit_count' => 5,
                'price' => 95000,
                'offer' => 84900,
                'colors' => ['Beige', 'Brown'],
                'tint' => [196, 160, 116],
            ],
            [
                'shop' => 'Stonecraft Studio',
                'subcategory' => 'Vitrified Tiles',
                'name' => 'Vitrified floor tile',
                'title' => 'Matte finish grey vitrified tile 600x600',
                'description' => 'Full-body vitrified tile with a matte anti-skid surface. Sold in boxes covering two square metres.',
                'unit' => 'sqft',
                'unit_count' => 2,
                'price' => 320000,
                'offer' => null,
                'colors' => ['Gray', 'Silver'],
                'tint' => [142, 142, 147],
            ],
            [
                'shop' => 'Lumen & Co',
                'subcategory' => 'Ceiling Lights',
                'name' => 'Linear LED profile',
                'title' => 'Recessed linear LED ceiling profile',
                'description' => 'Anodised aluminium profile with diffused opal cover. Includes 24W LED module with a warm white output for cove and false ceiling lighting.',
                'unit' => 'piece',
                'unit_count' => 1,
                'price' => 285000,
                'offer' => 259000,
                'colors' => ['White', 'Silver'],
                'tint' => [244, 238, 214],
            ],
            [
                'shop' => 'Lumen & Co',
                'subcategory' => 'Wall Lights',
                'name' => 'Brass wall sconce',
                'title' => 'Antique brass wall light for corridors',
                'description' => 'Solid brass sconce with a hand-rubbed antique finish. Dimmable, IP44 rated for semi-covered outdoor walls.',
                'unit' => 'piece',
                'unit_count' => 1,
                'price' => 410000,
                'offer' => 379000,
                'colors' => ['Gold', 'Brown'],
                'tint' => [201, 162, 39],
            ],
            [
                'shop' => 'Lumen & Co',
                'subcategory' => 'Outdoor Lights',
                'name' => 'Bollard garden light',
                'title' => 'Powder coated black bollard light',
                'description' => 'Cast aluminium bollard for garden paths. Powder coated in matte black with tempered glass diffuser.',
                'unit' => 'piece',
                'unit_count' => 1,
                'price' => 189000,
                'offer' => null,
                'colors' => ['Black'],
                'tint' => [40, 42, 46],
            ],
            [
                'shop' => 'Nordic Surfaces',
                'subcategory' => 'Wall Tiles',
                'name' => 'Subway wall tile',
                'title' => 'Glossy white subway tile for kitchens',
                'description' => 'Ceramic subway tile with a glossy white glaze. Sold per square metre, suitable for backsplash and bathroom walls.',
                'unit' => 'sqft',
                'unit_count' => 1,
                'price' => 78000,
                'offer' => 69900,
                'colors' => ['White'],
                'tint' => [248, 248, 246],
            ],
            [
                'shop' => 'Nordic Surfaces',
                'subcategory' => 'Stone Cladding',
                'name' => 'Slate cladding stone',
                'title' => 'Natural slate cladding for feature walls',
                'description' => 'Split-face natural slate cladding. Adds texture to exterior walls and water features. Supplied per square metre.',
                'unit' => 'sqft',
                'unit_count' => 1,
                'price' => 165000,
                'offer' => null,
                'colors' => ['Gray', 'Black', 'Multicolor'],
                'tint' => [96, 100, 104],
            ],
            [
                'shop' => 'Nordic Surfaces',
                'subcategory' => 'Interior Paint',
                'name' => 'Interior emulsion paint',
                'title' => 'Low odour interior emulsion, warm ivory',
                'description' => 'Water-based interior emulsion with excellent coverage and a washable matte finish. Price shown for a four litre pack.',
                'unit' => 'liter',
                'unit_count' => 4,
                'price' => 240000,
                'offer' => 219000,
                'colors' => ['White', 'Beige'],
                'tint' => [232, 224, 208],
            ],
        ];

        $colorIds = Color::pluck('id', 'name_key');

        foreach ($catalogue as $index => $row) {
            $shop = $shopModels[$row['shop']];
            $subcategory = Subcategory::where('name_key', NameNormalizer::key($row['subcategory']))->first();
            $unit = Unit::where('code', $row['unit'])->first();

            if (! $subcategory || ! $unit) {
                continue;
            }

            $product = Product::updateOrCreate(
                ['shop_id' => $shop->id, 'name' => $row['name']],
                [
                    'subcategory_id' => $subcategory->id,
                    'unit_id' => $unit->id,
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'unit_count' => $row['unit_count'],
                    'price_paise' => $row['price'],
                    'offer_price_paise' => $row['offer'],
                    'status' => Product::STATUS_PUBLISHED,
                    'published_at' => now()->subDays(count($catalogue) - $index),
                ],
            );

            $product->colors()->sync(
                collect($row['colors'])->map(fn ($name) => $colorIds[NameNormalizer::key($name)] ?? null)->filter()->all()
            );

            if ($product->images()->doesntExist()) {
                $this->makeImages($product, $row['tint']);
            }

            $projector->sync($product->refresh());
        }

        $this->command?->info('Seeded '.count($catalogue).' demo products across '.count($shops).' shops.');
    }

    /**
     * Generate review-only placeholder imagery (production uses uploaded photography).
     *
     * @param  array{0:int,1:int,2:int}  $tint
     */
    protected function makeImages(Product $product, array $tint): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        foreach ([0, 1] as $slot) {
            $width = 1200;
            $height = 900;
            $image = imagecreatetruecolor($width, $height);

            $shade = $slot === 0 ? 0 : -22;
            $base = imagecolorallocate(
                $image,
                max(0, min(255, $tint[0] + $shade)),
                max(0, min(255, $tint[1] + $shade)),
                max(0, min(255, $tint[2] + $shade)),
            );

            imagefill($image, 0, 0, $base);

            // Simple diagonal band so placeholder tiles are visually distinguishable.
            $band = imagecolorallocate($image, min(255, $tint[0] + 40), min(255, $tint[1] + 40), min(255, $tint[2] + 40));
            imagefilledpolygon($image, [0, 700, $width, 380, $width, 520, 0, 840], $band);

            $ink = imagecolorallocate($image, 40, 40, 44);
            imagestring($image, 5, 48, 48, $product->name, $ink);

            ob_start();
            imagejpeg($image, null, 82);
            $bytes = (string) ob_get_clean();
            imagedestroy($image);

            $name = \Illuminate\Support\Str::slug($product->name).'-'.$slot.'.jpg';
            $path = "products/{$name}";
            $thumb = "products/thumbs/{$name}";

            Storage::disk('public')->put($path, $bytes);

            $thumbImage = imagecreatetruecolor($width, $height);
            $source = imagecreatefromstring($bytes);
            $scaled = imagescale($source, 480);
            ob_start();
            imagejpeg($scaled, null, 78);
            Storage::disk('public')->put($thumb, (string) ob_get_clean());
            imagedestroy($thumbImage);
            imagedestroy($source);
            imagedestroy($scaled);

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'thumb_path' => $thumb,
                'sort_order' => $slot + 1,
            ]);
        }
    }
}
