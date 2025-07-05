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
        $client = app('elasticsearch');
        $index = env('ELASTICSEARCH_INDEX', 'test_productssss');

        Product::with('categories')->chunk(200, function ($products) use ($client, $index) {
            foreach ($products as $product) {
                $data = $product->toSearchableArray();

                $params = [
                    'index' => $index,
                    'id' => $product->id,
                    'body' => $data
                ];

                $client->index($params);
            }
            $this->info('Imported '.count($products).' products');
        });

        $this->info('All products imported!');
    }
}
