<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStockCloseDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_close_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_close_id')->constrained()->onDelete('cascade');
            $table->integer('product_id')->unsigned()->nullable();
            $table->string('product_name');
            $table->integer('stock')->default(0);
            $table->float('price',8,3)->default(0);
            $table->float('real_price',8,3)->default(0);
            $table->integer('purchases_quantity')->default(0);
            $table->float('price_all',8,3)->default(0);
            $table->float('product_real_price_all',8,3)->default(0);
            $table->string('air_source_sku');
            $table->string('sea_source_sku');
            $table->string('local_source_sku');
            $table->string('stock_location');
            $table->string('store_location');
            $table->string('link');
            $table->integer('air_source')->unsigned()->nullable();
            $table->integer('sea_source')->unsigned()->nullable();
            $table->integer('local_source')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_close_details');
    }
}
