<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Search\ElasticsearchEngine;

class CreateElasticsearchIndex extends Command
{
    protected $signature = 'elasticsearch:create-index';
    protected $description = 'Create Elasticsearch index with optimized mapping';

    public function handle()
    {
        $engine = app(ElasticsearchEngine::class);

        try {
            $indexName = env('ELASTICSEARCH_INDEX', 'test_productssss');
            $engine->createIndex($indexName);
            $this->info('Index created successfully');
        } catch (\Exception $e) {
            $this->error('Failed to create index: ' . $e->getMessage());
        }
    }
}
