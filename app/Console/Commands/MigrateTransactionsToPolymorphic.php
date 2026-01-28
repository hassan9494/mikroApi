<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Shop\Entities\Transaction;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\ReturnOrder;
use Modules\Common\Entities\Receipt;
use Modules\Common\Entities\ProjectReceipt;

class MigrateTransactionsToPolymorphic extends Command
{
    protected $signature = 'transactions:migrate-to-polymorphic
                            {--force : Run without confirmation}
                            {--chunk=100 : Number of records to process at once}';

    protected $description = 'Migrate existing transactions to polymorphic relationship including soft deleted records';

    public function handle()
    {
        if (!$this->option('force')) {
            $this->info('This command will migrate ALL transactions (including soft deleted) to polymorphic relationships.');
            $this->warn('Make sure you have a database backup before proceeding!');

            if (!$this->confirm('Do you wish to continue?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        $this->info('Starting migration to polymorphic relationships (including soft deleted)...');
        $this->newLine();

        $totalMigrated = 0;

        $totalMigrated += $this->migrateOrderTransactions();
        $this->newLine();

        $totalMigrated += $this->migrateReturnOrderTransactions();
        $this->newLine();

        $totalMigrated += $this->migrateReceiptTransactions();
        $this->newLine();

        $totalMigrated += $this->migrateProjectReceiptTransactions();
        $this->newLine();

        $this->showSummary();

        return 0;
    }

    protected function migrateOrderTransactions()
    {
        // Count both active and soft deleted transactions
        $count = Transaction::withTrashed()->whereNotNull('order_id')->count();

        if ($count === 0) {
            $this->info('No order transactions found to migrate.');
            return 0;
        }

        $this->info("Migrating {$count} order transactions (including soft deleted)...");

        $bar = $this->output->createProgressBar($count);
        $migrated = 0;

        $chunkSize = $this->option('chunk');

        Transaction::withTrashed()
            ->whereNotNull('order_id')
            ->where(function($query) {
                $query->whereNull('transactionable_id')
                    ->orWhereNull('transactionable_type');
            })
            ->chunkById($chunkSize, function ($transactions) use ($bar, &$migrated) {
                foreach ($transactions as $transaction) {
                    $transaction->transactionable_id = $transaction->order_id;
                    $transaction->transactionable_type = Order::class;

                    // Temporarily disable timestamps to preserve original timestamps
                    $originalTimestamps = $transaction->timestamps;
                    $transaction->timestamps = false;

                    $transaction->save();

                    // Restore timestamps setting
                    $transaction->timestamps = $originalTimestamps;

                    $bar->advance();
                    $migrated++;
                }
            });

        $bar->finish();
        $this->info("\n✓ Order transactions migrated successfully: {$migrated} records.");

        return $migrated;
    }

    protected function migrateReturnOrderTransactions()
    {
        $count = Transaction::withTrashed()->whereNotNull('return_order_id')->count();

        if ($count === 0) {
            $this->info('No return order transactions found to migrate.');
            return 0;
        }

        $this->info("Migrating {$count} return order transactions (including soft deleted)...");

        $bar = $this->output->createProgressBar($count);
        $migrated = 0;

        $chunkSize = $this->option('chunk');

        Transaction::withTrashed()
            ->whereNotNull('return_order_id')
            ->where(function($query) {
                $query->whereNull('transactionable_id')
                    ->orWhereNull('transactionable_type');
            })
            ->chunkById($chunkSize, function ($transactions) use ($bar, &$migrated) {
                foreach ($transactions as $transaction) {
                    $transaction->transactionable_id = $transaction->return_order_id;
                    $transaction->transactionable_type = ReturnOrder::class;

                    // Temporarily disable timestamps
                    $originalTimestamps = $transaction->timestamps;
                    $transaction->timestamps = false;

                    $transaction->save();

                    // Restore timestamps
                    $transaction->timestamps = $originalTimestamps;

                    $bar->advance();
                    $migrated++;
                }
            });

        $bar->finish();
        $this->info("\n✓ Return order transactions migrated successfully: {$migrated} records.");

        return $migrated;
    }

    protected function migrateReceiptTransactions()
    {
        // We need to also consider soft deleted receipts
        $receiptsCount = 0;

        // Check if Receipt model uses SoftDeletes
        $receiptModel = new Receipt();
        $usesSoftDeletes = method_exists($receiptModel, 'bootSoftDeletes');

        if ($usesSoftDeletes) {
            $receipts = Receipt::withTrashed()->whereNotNull('transaction_id')->get();
        } else {
            $receipts = Receipt::whereNotNull('transaction_id')->get();
        }

        $count = $receipts->count();

        if ($count === 0) {
            $this->info('No receipt transactions found to migrate.');
            return 0;
        }

        $this->info("Migrating {$count} receipt transactions (including soft deleted where applicable)...");

        $bar = $this->output->createProgressBar($count);
        $migrated = 0;

        foreach ($receipts as $receipt) {
            // Find the transaction (including soft deleted)
            $transaction = Transaction::withTrashed()->find($receipt->transaction_id);

            if ($transaction && (!$transaction->transactionable_id || !$transaction->transactionable_type)) {
                $transaction->transactionable_id = $receipt->id;
                $transaction->transactionable_type = Receipt::class;

                // Temporarily disable timestamps
                $originalTimestamps = $transaction->timestamps;
                $transaction->timestamps = false;

                $transaction->save();

                // Restore timestamps
                $transaction->timestamps = $originalTimestamps;

                $migrated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n✓ Receipt transactions migrated successfully: {$migrated} records.");

        return $migrated;
    }

    protected function migrateProjectReceiptTransactions()
    {
        // Check if ProjectReceipt model uses SoftDeletes
        $projectReceiptModel = new ProjectReceipt();
        $usesSoftDeletes = method_exists($projectReceiptModel, 'bootSoftDeletes');

        if ($usesSoftDeletes) {
            $projectReceipts = ProjectReceipt::withTrashed()->whereNotNull('transaction_id')->get();
        } else {
            $projectReceipts = ProjectReceipt::whereNotNull('transaction_id')->get();
        }

        $count = $projectReceipts->count();

        if ($count === 0) {
            $this->info('No project receipt transactions found to migrate.');
            return 0;
        }

        $this->info("Migrating {$count} project receipt transactions (including soft deleted where applicable)...");

        $bar = $this->output->createProgressBar($count);
        $migrated = 0;

        foreach ($projectReceipts as $receipt) {
            // Find the transaction (including soft deleted)
            $transaction = Transaction::withTrashed()->find($receipt->transaction_id);

            if ($transaction && (!$transaction->transactionable_id || !$transaction->transactionable_type)) {
                $transaction->transactionable_id = $receipt->id;
                $transaction->transactionable_type = ProjectReceipt::class;

                // Temporarily disable timestamps
                $originalTimestamps = $transaction->timestamps;
                $transaction->timestamps = false;

                $transaction->save();

                // Restore timestamps
                $transaction->timestamps = $originalTimestamps;

                $migrated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n✓ Project receipt transactions migrated successfully: {$migrated} records.");

        return $migrated;
    }

    protected function showSummary()
    {
        $this->info('Migration Summary (including soft deleted records):');

        $summary = [
            ['Orders', Transaction::withTrashed()->where('transactionable_type', Order::class)->count()],
            ['Return Orders', Transaction::withTrashed()->where('transactionable_type', ReturnOrder::class)->count()],
            ['Receipts', Transaction::withTrashed()->where('transactionable_type', Receipt::class)->count()],
            ['Project Receipts', Transaction::withTrashed()->where('transactionable_type', ProjectReceipt::class)->count()],
        ];

        $totalMigrated = Transaction::withTrashed()->whereNotNull('transactionable_type')->count();
        $totalTransactions = Transaction::withTrashed()->count();
        $remaining = $totalTransactions - $totalMigrated;

        $summary[] = ['Total Migrated', $totalMigrated];
        $summary[] = ['Total Transactions', $totalTransactions];
        $summary[] = ['Remaining (No Type)', $remaining];

        $this->table(
            ['Model Type', 'Count'],
            $summary
        );

        // Show soft deleted counts separately
        $deletedCount = Transaction::onlyTrashed()->count();
        $deletedMigrated = Transaction::onlyTrashed()->whereNotNull('transactionable_type')->count();

        $this->info("Soft Deleted Transactions: {$deletedCount} total, {$deletedMigrated} migrated");

        $this->info('✅ Migration completed!');
        $this->info('Note: Old foreign keys (order_id, return_order_id) are still in place for backward compatibility.');
    }
}
