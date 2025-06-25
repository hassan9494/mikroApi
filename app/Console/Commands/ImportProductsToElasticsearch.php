<?php

namespace App\Console\Commands;

// app/Console/Commands/ImportProductsToElasticsearch.php
use Illuminate\Console\Command;
use Modules\Shop\Entities\Product;

class ImportProductsToElasticsearch extends Command
{
    protected $signature = 'elasticsearch:import-products';
    protected $description = 'Import products to Elasticsearch';

    public function handle()
    {
        $client = app('custom-elastic');
        $index = 'test_products';

        Product::with('categories')->chunk(200, function ($products) use ($client, $index) {
            foreach ($products as $product) {
                $data = $product->toSearchableArray();
                $client->indexDocument($index, $product->id, $data);
            }
            $this->info('Imported '.count($products).' products');
        });

        $this->info('All products imported!');
    }
}
