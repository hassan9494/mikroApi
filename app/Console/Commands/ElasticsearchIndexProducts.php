<?php
// app/Console/Commands/ElasticsearchIndexProducts.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Shop\Entities\Product;

class ElasticsearchIndexProducts extends Command
{
    protected $signature = 'elasticsearch:index-products';
    protected $description = 'Index all products to Elasticsearch';

    public function handle()
    {
        $products = Product::all();
        $bar = $this->output->createProgressBar(count($products));

        $this->info("Indexing {$products->count()} products...");

        foreach ($products as $product) {
            try {
                $product->searchable();
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nFailed to index product {$product->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->info("\nIndexing completed successfully!");
    }
}
