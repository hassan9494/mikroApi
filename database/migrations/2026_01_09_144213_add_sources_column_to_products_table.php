<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourcesColumnToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('air_source_id')->unsigned()->nullable();
            $table->string('air_source_sku')->nullable();
            $table->integer('air_min_qty')->default(0);
            $table->integer('air_order_qty')->default(0);
            $table->integer('sea_source_id')->unsigned()->nullable();
            $table->string('sea_source_sku')->nullable();
            $table->integer('sea_min_qty')->default(0);
            $table->integer('sea_order_qty')->default(0);
            $table->integer('local_source_id')->unsigned()->nullable();
            $table->string('local_source_sku')->nullable();
            $table->integer('local_min_qty')->default(0);
            $table->integer('local_order_qty')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
}
