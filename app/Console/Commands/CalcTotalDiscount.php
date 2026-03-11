<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalcTotalDiscount extends Command
{
    protected $signature = 'order:calc-total-discount
                            {--dry-run : Check what will be changed without saving}
                            {--id= : Fix specific order ID}';

    protected $description = 'Calculate total_discount (discount + points_discount) for existing orders and order_products';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be saved');
        }

        $this->info('Starting total_discount calculation...');

        // --- Orders ---
        $this->newLine();
        $this->info('=== Processing Orders ===');

        $orderQuery = DB::table('orders');

        if ($id = $this->option('id')) {
            $orderQuery->where('id', $id);
            $this->info("Target: Order ID {$id}");
        } else {
            $this->info("Target: ALL orders");
        }

        $orders = $orderQuery->get();
        $ordersUpdated = 0;
        $ordersSkipped = 0;

        foreach ($orders as $order) {
            $discount = (float) ($order->discount ?? 0);
            $pointsDiscount = (float) ($order->points_discount ?? 0);
            $newTotalDiscount = round($discount + $pointsDiscount, 3);
            $currentTotalDiscount = (float) ($order->total_discount ?? 0);

            if (abs($currentTotalDiscount - $newTotalDiscount) > 0.0001) {
                $this->line("Order #{$order->id}: total_discount {$currentTotalDiscount} → {$newTotalDiscount} (discount: {$discount} + points_discount: {$pointsDiscount})");

                if (!$isDryRun) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['total_discount' => $newTotalDiscount]);
                }

                $ordersUpdated++;
            } else {
                $ordersSkipped++;
            }
        }

        $this->info("Orders updated: {$ordersUpdated}, skipped: {$ordersSkipped}");

        // --- Order Products ---
        $this->newLine();
        $this->info('=== Processing Order Products ===');

        $productQuery = DB::table('order_products');

        if ($id = $this->option('id')) {
            $productQuery->where('order_id', $id);
            $this->info("Target: Products for Order ID {$id}");
        } else {
            $this->info("Target: ALL order products");
        }

        $products = $productQuery->get();
        $productsUpdated = 0;
        $productsSkipped = 0;

        foreach ($products as $product) {
            $discount = (float) ($product->discount ?? 0);
            $pointsDiscount = (float) ($product->points_discount ?? 0);
            $newTotalDiscount = round($discount + $pointsDiscount, 3);
            $currentTotalDiscount = (float) ($product->total_discount ?? 0);

            if (abs($currentTotalDiscount - $newTotalDiscount) > 0.0001) {
                $this->line("Order Product (order_id: {$product->order_id}, product_id: {$product->product_id}): total_discount {$currentTotalDiscount} → {$newTotalDiscount}");

                if (!$isDryRun) {
                    DB::table('order_products')
                        ->where('order_id', $product->order_id)
                        ->where('product_id', $product->product_id)
                        ->update(['total_discount' => $newTotalDiscount]);
                }

                $productsUpdated++;
            } else {
                $productsSkipped++;
            }
        }

        $this->info("Order products updated: {$productsUpdated}, skipped: {$productsSkipped}");

        // --- Summary ---
        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Orders: {$ordersUpdated} updated, {$ordersSkipped} skipped");
        $this->line("Order Products: {$productsUpdated} updated, {$productsSkipped} skipped");

        if ($isDryRun) {
            $this->warn("\nThis was a dry run. Run without --dry-run to apply changes.");
        } else {
            $this->info("\nAll changes applied successfully!");
        }

        return 0;
    }
}
