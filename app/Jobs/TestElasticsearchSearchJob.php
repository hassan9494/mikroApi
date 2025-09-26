<?php
// app/Jobs/TestElasticsearchSearchJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class TestElasticsearchSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $searchTerm;
    protected $iteration;
    protected $totalIterations;

    /**
     * Create a new job instance.
     */
    public function __construct($searchTerm, $iteration, $totalIterations)
    {
        $this->searchTerm = $searchTerm;
        $this->iteration = $iteration;
        $this->totalIterations = $totalIterations;
        $this->onQueue('elasticsearch-test'); // Separate queue for testing
    }

    /**
     * Execute the job.
     */
    public function handle(ProductRepositoryInterface $productRepository): void
    {
        $startTime = microtime(true);

        try {
            // Simulate different search scenarios
            $category = $this->getRandomCategory();
            $filter = $this->getRandomFilter();
            $inStock = $this->getRandomStockStatus();
            $limit = $this->getRandomLimit();

            // Execute the search using your existing repository method
            $results = $productRepository->search(
                $this->searchTerm,
                null,
                20,
                null,
                false
            );

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

            // Log performance metrics
            $this->logPerformance($executionTime, $results->total(), $this->iteration);

            // Log every 1000th iteration for progress tracking
            if ($this->iteration % 1000 === 0) {
                Log::info("Elasticsearch Test Progress", [
                    'iteration' => $this->iteration,
                    'total_iterations' => $this->totalIterations,
                    'progress_percentage' => round(($this->iteration / $this->totalIterations) * 100, 2),
                    'search_term' => $this->searchTerm,
                    'execution_time_ms' => $executionTime,
                    'total_results' => $results->total()
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Elasticsearch Test Failed", [
                'iteration' => $this->iteration,
                'search_term' => $this->searchTerm,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get random category for testing
     */
    private function getRandomCategory()
    {
        $categories = [
            null,
            'electronics',
            'tools',
            'components',
            'sensors',
            'new_product',
            'arduino',
            'raspberry-pi'
        ];

        return $categories[array_rand($categories)];
    }

    /**
     * Get random filter for testing
     */
    private function getRandomFilter()
    {
        $filters = [
            null,
            'new-item',
            'old-item',
            'price-high',
            'price-low',
            'sale'
        ];

        return $filters[array_rand($filters)];
    }

    /**
     * Get random stock status
     */
    private function getRandomStockStatus()
    {
        return (bool) rand(0, 1);
    }

    /**
     * Get random limit
     */
    private function getRandomLimit()
    {
        $limits = [10, 20, 50, 100];
        return $limits[array_rand($limits)];
    }

    /**
     * Log performance metrics
     */
    private function logPerformance($executionTime, $totalResults, $iteration)
    {
        // Store performance data in cache for summary reporting
        $performanceData = cache()->get('elasticsearch_test_performance', []);

        $performanceData[] = [
            'iteration' => $iteration,
            'execution_time_ms' => $executionTime,
            'total_results' => $totalResults,
            'timestamp' => now()->toISOString()
        ];

        // Keep only last 1000 records to prevent memory issues
        if (count($performanceData) > 1000) {
            $performanceData = array_slice($performanceData, -1000);
        }

        cache()->put('elasticsearch_test_performance', $performanceData, now()->addHours(24));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Elasticsearch Test Job Failed', [
            'search_term' => $this->searchTerm,
            'iteration' => $this->iteration,
            'error' => $exception->getMessage()
        ]);
    }
}
