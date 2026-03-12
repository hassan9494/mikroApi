<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Shop\Entities\Product;

class SetShelfCapacity extends Command
{
    protected $signature = 'product:set-capacity
                            {--min= : Min capacity value to set}
                            {--max= : Max capacity value to set}
                            {--only-empty : Only update products where min/max are both 0}
                            {--dry-run : Check what will be changed without saving}
                            {--id= : Target specific product ID}
                            {--ids= : Target multiple product IDs (comma separated: 1,2,3)}
                            {--range= : Target range of IDs (format: start-end, e.g., 1-10)}';

    protected $description = 'Set min_capacity and max_capacity for products';

    public function handle()
    {
        $min = $this->option('min');
        $max = $this->option('max');

        if (is_null($min) && is_null($max)) {
            $this->error('You must provide at least --min or --max value.');
            return 1;
        }

        if (!is_null($min) && !is_null($max) && (int) $min > (int) $max) {
            $this->error('Min capacity cannot be greater than max capacity.');
            return 1;
        }

        $isDryRun = $this->option('dry-run');
        $onlyEmpty = $this->option('only-empty');

        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be saved');
        }

        $this->info('Starting shelf capacity setup...');

        $updates = [];
        if (!is_null($min)) {
            $updates['min_capacity'] = (int) $min;
            $this->info("Min capacity: {$min}");
        }
        if (!is_null($max)) {
            $updates['max_capacity'] = (int) $max;
            $this->info("Max capacity: {$max}");
        }

        // Build query based on options
        $query = Product::query();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
            $this->info("Target: Product ID {$id}");
        } elseif ($ids = $this->option('ids')) {
            $idArray = array_map('intval', explode(',', $ids));
            $query->whereIn('id', $idArray);
            $this->info("Target: Product IDs " . implode(', ', $idArray));
        } elseif ($range = $this->option('range')) {
            if (strpos($range, '-') !== false) {
                [$start, $end] = explode('-', $range);
                $query->whereBetween('id', [(int) $start, (int) $end]);
                $this->info("Target: Product IDs from {$start} to {$end}");
            } else {
                $this->error('Invalid range format. Use: --range=1-10');
                return 1;
            }
        } else {
            $this->info("Target: ALL products");
        }

        if ($onlyEmpty) {
            $query->where('min_capacity', 0)->where('max_capacity', 0);
            $this->info("Filter: Only products with no capacity set");
        }

        $total = $query->count();
        $updated = 0;
        $skipped = 0;

        $query->chunk(100, function ($products) use ($updates, &$updated, &$skipped, $isDryRun) {
            foreach ($products as $product) {
                $oldMin = (int) $product->min_capacity;
                $oldMax = (int) $product->max_capacity;

                $newMin = $updates['min_capacity'] ?? $oldMin;
                $newMax = $updates['max_capacity'] ?? $oldMax;

                // Skip if values are already the same
                if ($oldMin === $newMin && $oldMax === $newMax) {
                    $skipped++;
                    continue;
                }

                $this->info("Product {$product->id} ({$product->name}):");
                if (isset($updates['min_capacity'])) {
                    $this->line("  - min_capacity: {$oldMin} → {$newMin}");
                }
                if (isset($updates['max_capacity'])) {
                    $this->line("  - max_capacity: {$oldMax} → {$newMax}");
                }

                if (!$isDryRun) {
                    $product->update($updates);
                    $this->line("  [UPDATED]");
                } else {
                    $this->line("  [WOULD UPDATE]");
                }

                $updated++;
            }
        });

        $this->newLine();
        $this->info("Summary:");
        $this->line("Total products processed: {$total}");
        $this->line("Updated: {$updated}");
        $this->line("Skipped (no changes needed): {$skipped}");

        if ($isDryRun) {
            $this->warn("\nThis was a dry run. Run without --dry-run to apply changes.");
        } else {
            $this->info("\nAll changes applied successfully!");
        }

        return 0;
    }
}
