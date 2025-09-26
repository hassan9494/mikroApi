<?php
// app/Console/Commands/RunElasticsearchStressTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\TestElasticsearchSearchJob;
use Modules\Shop\Entities\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RunElasticsearchStressTest extends Command
{
    protected $signature = 'elasticsearch:stress-test
                            {iterations=50000 : Number of search iterations to run}
                            {--concurrent=10 : Number of concurrent jobs}
                            {--batch-size=5000 : Size of each batch}';

    protected $description = 'Run Elasticsearch stress test with multiple search iterations';

    public function handle()
    {
        $totalIterations = (int) $this->argument('iterations');
        $concurrentJobs = (int) $this->option('concurrent');
        $batchSize = (int) $this->option('batch-size');

        $this->info("Starting Elasticsearch stress test...");
        $this->info("Total iterations: {$totalIterations}");
        $this->info("Concurrent jobs: {$concurrentJobs}");
        $this->info("Batch size: {$batchSize}");

        // Clear previous performance data
        Cache::forget('elasticsearch_test_performance');
        Cache::forget('elasticsearch_test_summary');

        // Get search terms from actual product data
        $searchTerms = $this->getSearchTerms();

        $startTime = microtime(true);
        $jobsDispatched = 0;

        $progressBar = $this->output->createProgressBar($totalIterations);
        $progressBar->start();

        // Dispatch jobs in batches to control memory usage
        for ($batch = 0; $batch < ceil($totalIterations / $batchSize); $batch++) {
            $jobs = [];

            $currentBatchSize = min($batchSize, $totalIterations - ($batch * $batchSize));

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $iterationNumber = ($batch * $batchSize) + $i + 1;
                $searchTerm = $searchTerms[array_rand($searchTerms)];

                $jobs[] = new TestElasticsearchSearchJob(
                    $searchTerm,
                    $iterationNumber,
                    $totalIterations
                );

                $jobsDispatched++;
                $progressBar->advance();
            }

            // Dispatch batch with controlled concurrency
            $batch = collect($jobs)->chunk($concurrentJobs);

            foreach ($batch as $chunk) {
                foreach ($chunk as $job) {
                    dispatch($job);
                }

                // Small delay between chunks to prevent overwhelming the queue
                sleep(1);
            }

            // Clear memory between batches
            unset($jobs);
            gc_collect_cycles();
        }

        $progressBar->finish();

        $totalTime = round(microtime(true) - $startTime, 2);

        $this->info("\n");
        $this->info("✅ Stress test initiated successfully!");
        $this->info("📊 Jobs dispatched: {$jobsDispatched}");
        $this->info("⏱️  Setup time: {$totalTime}s");
        $this->info("👀 Monitor your queue worker and logs for progress");
        $this->info("💡 Run: php artisan queue:work --queue=elasticsearch-test");
        $this->info("📈 Run: php artisan elasticsearch:test-results to see summary");
    }

    /**
     * Get realistic search terms from products
     */
    private function getSearchTerms()
    {
        $this->info("Fetching search terms from database...");

        // Get actual product names, SKUs, and categories for realistic testing
        $productNames = Product::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->pluck('name')
            ->take(1000)
            ->toArray();

        $productSkus = Product::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->take(500)
            ->toArray();

        $commonTerms = [
            'arduino', 'sensor', 'module', 'board', 'esp32', 'raspberry', 'pi',
            'motor', 'led', 'display', 'screen', 'cable', 'connector', 'switch',
            'resistor', 'capacitor', 'transistor', 'diode', 'ic', 'microcontroller',
            'wire', 'battery', 'power', 'usb', 'ethernet', 'wifi', 'bluetooth',
            'sensor temperature', 'sensor humidity', 'sensor motion', 'sensor light',
            'motor driver', 'led strip', 'display lcd', 'arduino uno', 'esp32 cam',
            'raspberry pi 4', 'power supply', 'breadboard', 'jumper wires'
        ];

        // Combine all search terms
        $searchTerms = array_merge($productNames, $productSkus, $commonTerms);

        // Add some partial terms for testing
        $partialTerms = [];
        foreach ($searchTerms as $term) {
            if (strlen($term) > 5) {
                $partialTerms[] = substr($term, 0, 3);
                $partialTerms[] = substr($term, 0, 4);
            }
        }

        $allTerms = array_merge($searchTerms, $partialTerms);

        // Remove duplicates and empty values
        $allTerms = array_filter(array_unique($allTerms));

        $this->info("Generated " . count($allTerms) . " unique search terms");

        return array_values($allTerms);
    }
}
