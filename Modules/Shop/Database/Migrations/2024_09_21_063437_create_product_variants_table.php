<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ProductVariants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('short_description')->nullable();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->json('price')->nullable();
            $table->integer('stock')->default(0);
            $table->json('options')->nullable();
            $table->integer('listPriority')->default(0);
            $table->integer('maxCartAmount')->default(0);
            $table->integer('min_qty')->default(0);
            $table->boolean('is_retired')->default(0);
            $table->string('source')->nullable();
            $table->string('barcode')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('product_id')->references('id')->on('products')->cascadeOnDelete();
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
        Schema::dropIfExists('ProductVariants');
    }
}
