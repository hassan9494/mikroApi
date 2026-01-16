<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockHistoryToTransferOrderProducts extends Migration
{
    public function up()
    {
        Schema::table('transfer_order_products', function (Blueprint $table) {
            $table->integer('stock_before')->nullable()->after('quantity');
            $table->integer('stock_after')->nullable()->after('stock_before');
            $table->integer('stock_available_before')->nullable()->after('stock_after');
            $table->integer('stock_available_after')->nullable()->after('stock_available_before');
            $table->integer('store_available_before')->nullable()->after('stock_available_after');
            $table->integer('store_available_after')->nullable()->after('store_available_before');
        });
    }

    public function down()
    {
        Schema::table('transfer_order_products', function (Blueprint $table) {
            $table->dropColumn([
                'stock_before',
                'stock_after',
                'stock_available_before',
                'stock_available_after',
                'store_available_before',
                'store_available_after'
            ]);
        });
    }
}
