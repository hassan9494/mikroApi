<?php

namespace Modules\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\Product;

class ShopDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Order::query()->delete();
        Model::unguard();
        $products = Product::all();
        Order::factory(1000)
            ->create()
            ->each(function($model) use ($products) {
                $sync = [];
                foreach ($products->random(rand(1, 5)) as $product) {
                    $sync[$product->id] = [
                        'quantity' => rand(1, 5),
                        'price' => $product->price->normal_price,
                        'real_price' => $product->price->real_price,
                    ];
                }
                $model->products()->attach($sync);
            });
        Order::all()->each(function($model) {
            $model->save();
        });

        // $this->call("OthersTableSeeder");
    }
}
