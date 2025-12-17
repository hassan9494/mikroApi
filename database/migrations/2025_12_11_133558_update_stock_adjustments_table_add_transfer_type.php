<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateStockAdjustmentsTableAddTransferType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Method 1: Using DB::statement for MySQL
//        DB::statement("ALTER TABLE `stock_adjustments`
//            MODIFY COLUMN `adjustment_type` ENUM('increase', 'decrease', 'transfer') NOT NULL");

        // Method 2: Using Schema::table (if supported)
        // Schema::table('stock_adjustments', function (Blueprint $table) {
        //     $table->enum('adjustment_type', ['increase', 'decrease', 'transfer'])->change();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE `stock_adjustments`
            MODIFY COLUMN `adjustment_type` ENUM('increase', 'decrease') NOT NULL");
    }
}
