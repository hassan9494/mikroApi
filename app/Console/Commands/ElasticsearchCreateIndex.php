<?php
// app/Console/Commands/ElasticsearchCreateIndex.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Search\ElasticsearchEngine;

class ElasticsearchCreateIndex extends Command
{
    protected $signature = 'elasticsearch:create-index';
    protected $description = 'Create Elasticsearch index with optimized mapping';

    public function handle()
    {
        $engine = app(ElasticsearchEngine::class);
        $indexName = env('ELASTICSEARCH_INDEX', 'test_productssss');

        try {
            // Delete existing index if it exists
            try {
                $engine->deleteIndex($indexName);
                $this->info("Deleted existing index: {$indexName}");
            } catch (\Exception $e) {
                // Index didn't exist, continue
            }

            $engine->createIndex($indexName);
            $this->info("Index created successfully: {$indexName}");
        } catch (\Exception $e) {
            $this->error('Failed to create index: ' . $e->getMessage());
        }
    }
}
