<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
//        Schema::table('stock_adjustments', function (Blueprint $table) {
//            $table->enum('adjustment_location', ['total', 'stock_available', 'store_available'])
//                ->default('total')
//                ->after('adjustment_type');
//
//            $table->integer('stock_available_before')->nullable()->after('stock_before');
//            $table->integer('stock_available_after')->nullable()->after('stock_available_before');
//            $table->integer('store_available_before')->nullable()->after('stock_available_after');
//            $table->integer('store_available_after')->nullable()->after('store_available_before');
//
//            $table->integer('transfer_to_location')->nullable()->after('adjustment_location');
//            $table->integer('transfer_from_location')->nullable()->after('transfer_to_location');
//        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'adjustment_location',
                'stock_available_before',
                'stock_available_after',
                'store_available_before',
                'store_available_after',
                'transfer_to_location',
                'transfer_from_location'
            ]);
        });
    }
};
