<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('invoice_products', function (Blueprint $table) {
            $table->integer('stock_available_qty')->default(0)->after('quantity');
            $table->integer('store_available_qty')->default(0)->after('stock_available_qty');
        });
    }

    public function down()
    {
        Schema::table('invoice_products', function (Blueprint $table) {
            $table->dropColumn(['stock_available_qty', 'store_available_qty']);
        });
    }
};
