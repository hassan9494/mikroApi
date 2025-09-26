<?php
// app/Console/Commands/ShowElasticsearchTestResults.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ShowElasticsearchTestResults extends Command
{
    protected $signature = 'elasticsearch:test-results';
    protected $description = 'Show Elasticsearch stress test results summary';

    public function handle()
    {
        $performanceData = Cache::get('elasticsearch_test_performance', []);

        if (empty($performanceData)) {
            $this->info("No test results found. Run the stress test first.");
            return;
        }

        $totalSearches = count($performanceData);
        $executionTimes = array_column($performanceData, 'execution_time_ms');
        $totalResults = array_column($performanceData, 'total_results');

        $stats = [
            'Total Searches' => $totalSearches,
            'Average Response Time' => round(array_sum($executionTimes) / $totalSearches, 2) . ' ms',
            'Min Response Time' => round(min($executionTimes), 2) . ' ms',
            'Max Response Time' => round(max($executionTimes), 2) . ' ms',
            '95th Percentile' => round($this->calculatePercentile($executionTimes, 95), 2) . ' ms',
            'Average Results per Search' => round(array_sum($totalResults) / $totalSearches, 2),
            'Successful Searches' => $totalSearches,
            'Failed Searches' => 0, // You can track failures separately
        ];

        $this->info("====================================");
        $this->info("   ELASTICSEARCH STRESS TEST RESULTS");
        $this->info("====================================");

        foreach ($stats as $key => $value) {
            $this->info(str_pad($key, 25) . ": " . $value);
        }

        $this->info("\nResponse Time Distribution:");
        $this->showHistogram($executionTimes);
    }

    private function calculatePercentile($data, $percentile)
    {
        sort($data);
        $index = ($percentile / 100) * (count($data) - 1);

        if (floor($index) == $index) {
            return $data[$index];
        }

        $lower = $data[floor($index)];
        $upper = $data[ceil($index)];

        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    private function showHistogram($data)
    {
        $buckets = [
            '0-10 ms' => 0,
            '10-50 ms' => 0,
            '50-100 ms' => 0,
            '100-500 ms' => 0,
            '500-1000 ms' => 0,
            '1000+ ms' => 0
        ];

        foreach ($data as $time) {
            if ($time <= 10) $buckets['0-10 ms']++;
            elseif ($time <= 50) $buckets['10-50 ms']++;
            elseif ($time <= 100) $buckets['50-100 ms']++;
            elseif ($time <= 500) $buckets['100-500 ms']++;
            elseif ($time <= 1000) $buckets['500-1000 ms']++;
            else $buckets['1000+ ms']++;
        }

        foreach ($buckets as $range => $count) {
            $percentage = round(($count / count($data)) * 100, 1);
            $bar = str_repeat('█', round($percentage / 2));
            $this->info(sprintf("%-10s: %5d searches %5.1f%% %s", $range, $count, $percentage, $bar));
        }
    }
}
