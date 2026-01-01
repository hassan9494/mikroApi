<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Shop\Entities\Product;
use Illuminate\Support\Facades\DB;

class FixProductStock extends Command
{
    protected $signature = 'product:fix-stock
                            {--dry-run : Check what will be changed without saving}
                            {--id= : Fix specific product ID}
                            {--ids= : Fix multiple product IDs (comma separated: 1,2,3)}
                            {--range= : Fix range of IDs (format: start-end, e.g., 1-10)}';

    protected $description = 'Fix stock_available and store_available for products';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be saved');
        }

        $this->info('Starting stock fix...');

        // Build query based on options
        $query = Product::query();

        // Option 1: Single ID
        if ($id = $this->option('id')) {
            $query->where('id', $id);
            $this->info("Target: Product ID {$id}");
        }
        // Option 2: Multiple IDs
        elseif ($ids = $this->option('ids')) {
            $idArray = array_map('intval', explode(',', $ids));
            $query->whereIn('id', $idArray);
            $this->info("Target: Product IDs " . implode(', ', $idArray));
        }
        // Option 3: Range of IDs
        elseif ($range = $this->option('range')) {
            if (strpos($range, '-') !== false) {
                [$start, $end] = explode('-', $range);
                $query->whereBetween('id', [(int)$start, (int)$end]);
                $this->info("Target: Product IDs from {$start} to {$end}");
            } else {
                $this->error('Invalid range format. Use: --range=1-10');
                return 1;
            }
        }
        // Option 4: All products (default)
        else {
            $this->info("Target: ALL products");
        }

        $total = $query->count();
        $updatedCase1 = 0; // Products with stock but no split
        $updatedCase2 = 0; // Products with wrong total
        $skipped = 0;

        $query->chunk(100, function ($products) use (&$updatedCase1, &$updatedCase2, &$skipped, $isDryRun) {
            foreach ($products as $product) {
                $stockAvailable = (float) ($product->stock_available ?? 0);
                $storeAvailable = (float) ($product->store_available ?? 0);
                $totalStock = (float) ($product->stock ?? 0);

                $changes = [];

                // Case 1: Both available stocks are 0/null but total stock exists
                if ($stockAvailable == 0 && $storeAvailable == 0 && $totalStock > 0) {
                    $changes['store_available'] = $totalStock;
                    $changes['stock_available'] = 0;
                    $updatedCase1++;
                }
                // Case 2: Recalculate total if it doesn't match sum
                elseif ($totalStock != ($stockAvailable + $storeAvailable)) {
                    $changes['stock'] = $stockAvailable + $storeAvailable;
                    $updatedCase2++;
                }

                // Apply changes
                if (!empty($changes)) {
                    $this->info("Product {$product->id} ({$product->name}):");
                    foreach ($changes as $field => $newValue) {
                        $oldValue = $product->{$field};
                        $this->line("  - {$field}: {$oldValue} → {$newValue}");
                    }

                    if (!$isDryRun) {
                        $product->update($changes);
                        $this->line("  [UPDATED]");
                    } else {
                        $this->line("  [WOULD UPDATE]");
                    }
                } else {
                    $skipped++;
                }
            }
        });

        $this->newLine();
        $this->info("Summary:");
        $this->line("Total products processed: {$total}");
        $this->line("Fixed products with no split: {$updatedCase1}");
        $this->line("Fixed products with wrong total: {$updatedCase2}");
        $this->line("Skipped (no changes needed): {$skipped}");

        if ($isDryRun) {
            $this->warn("\nThis was a dry run. Run without --dry-run to apply changes.");
        } else {
            $this->info("\nAll changes applied successfully!");
        }

        return 0;
    }
}
