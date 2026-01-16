<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
//        Schema::table('stock_adjustments', function (Blueprint $table) {
//            $table->integer('stock_before')->nullable()->after('quantity');
//            $table->integer('stock_after')->nullable()->after('stock_before');
//            $table->integer('stock_after_calculated')->nullable()->after('stock_after');
//        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn(['stock_before', 'stock_after', 'stock_after_calculated']);
        });
    }
};
