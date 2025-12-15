<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateStockAdjustmentsAddTransferToAdjustmentLocation extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `adjustment_location` ENUM('total', 'stock_available', 'store_available', 'transfer') NOT NULL DEFAULT 'total'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `adjustment_location` ENUM('total', 'stock_available', 'store_available') NOT NULL DEFAULT 'total'");
    }
}
