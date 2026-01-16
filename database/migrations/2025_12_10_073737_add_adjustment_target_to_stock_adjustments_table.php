<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->enum('adjustment_target', ['total', 'stock_available', 'store_available'])
                ->default('total')
                ->after('adjustment_type');

            $table->integer('target_before')->nullable()->after('stock_after_calculated');
            $table->integer('target_after')->nullable()->after('target_before');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn(['adjustment_target', 'target_before', 'target_after']);
        });
    }
};
