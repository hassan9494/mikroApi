<?php

namespace Database\Seeders;

use App\Models\User;
use DB;
use Illuminate\Database\Seeder;
use Modules\Shop\Entities\Category;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductCategory;
use Modules\Shop\Entities\ProductRelated;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
//        $this->call(BoardSeeder::class);
        $this->call(PaymentMethodSeeder::class);
//        Role::create(['name' => 'super']);
//        Role::create(['name' => 'admin']);
//        Role::create(['name' => 'user']);
//
//        $user = User::factory(1)->create([
//            'email' => 'admin@mikroelectron.com'
//        ]);
//        $user[0]->assignRole('super', 'admin', 'user');
//
////        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
//        \DB::table('product_category')->truncate();
//        \DB::table('categories')->truncate();
//        \DB::table('products')->truncate();
//        \DB::table('product_related')->truncate();
//        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

//        $categories = json_decode(file_get_contents(storage_path() . "/data/categories.json"), true);
//        foreach ($categories as $item) {
//            Category::create([
//                'id' => $item['id'],
//                'title' => $item['name'],
//                'slug' => strtolower($item['link']),
//                'order' => $item['order'] < 0 ? 0 : $item['order']
//            ]);
//        }
//
//        $products = json_decode(file_get_contents(storage_path() . "/data/products.json"), true);
//        foreach ($products as $item) {
//            $tags = explode(',', $item['tags']);
//            $prices = json_decode($item['price']);
//            $price = [
//                'normal_price' => $prices->prices[0]->NormalPrice,
//                'real_price' => $item['real_price'],
//                'sale_price' => $prices->prices[0]->SalePrice,
//                'distributor_price' => $prices->prices[0]->NormalPrice,
//            ];
//            $product = Product::create([
//                'id' => $item['id'],
//                'name' => $item['name'],
//                'sku' => strtolower($item['link']),
//                'quantity' => $item['qty'],
////                'kit' => $item['IsKit'],
//                'description' => $item['Description'],
//                'documents' => $item['Documents'],
//                'code' => $item['MikroCode'],
//                'features' => $item['Feature'],
//                'price' => $price
//            ]);
//
////            foreach (json_decode($item['images'])  ?? [] as $image)
////            {
////                try {
////                    $product->addMediaFromDisk("X4/$image")->toMediaCollection();
////                } catch (\Exception $exception) {
////
////                }
////            }
//        }
//
//        $categories = json_decode(file_get_contents(storage_path() . "/data/product_category.json"), true);
//        foreach ($categories as $item) {
//            ProductCategory::create([
//                'product_id' => $item['product_id'],
//                'category_id' => $item['category_id'],
//            ]);
//        }
////
//        $relateds = json_decode(file_get_contents(storage_path() . "/data/related.json"), true);
//        foreach ($relateds as $item) {
//            ProductRelated::create([
//                'parent_id' => $item['product1_id'],
//                'child_id' => $item['product2_id'],
//            ]);
//        }
//
//        $media = json_decode(file_get_contents(storage_path() . "/data/microelektron_media.json"), true);
//        foreach ($media as $item) {
//            $item['disk'] = 's3';
//            $item['conversions_disk'] = 's3';
//            Media::create($item);
//        }

    }
}
