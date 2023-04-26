<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductKitTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_kit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')
                ->references('id')
                ->on('products');
            $table->foreignId('product_id')
                ->references('id')
                ->on('products');
            $table->integer('quantity')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_kit');
    }
}
