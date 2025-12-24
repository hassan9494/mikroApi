<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateStockAdjustmentsTransferLocationColumns extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change transfer_from_location from INT to VARCHAR or ENUM
        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `transfer_from_location` VARCHAR(50) NULL");

        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `transfer_to_location` VARCHAR(50) NULL");

        // If you want to use ENUM instead:
        // DB::statement("ALTER TABLE `stock_adjustments`
        //     MODIFY COLUMN `transfer_from_location` ENUM('stock_available', 'store_available') NULL");
        // DB::statement("ALTER TABLE `stock_adjustments`
        //     MODIFY COLUMN `transfer_to_location` ENUM('stock_available', 'store_available') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to integer
        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `transfer_from_location` INT(11) NULL");

        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `transfer_to_location` INT(11) NULL");
    }
}
