<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->longText('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('features')->nullable();
            $table->longText('documents')->nullable();
            $table->longText('code')->nullable();
            $table->json('price')->nullable();
            $table->json('kit')->nullable();
            $table->json('datasheets')->nullable();
            $table->json('shipping')->nullable();
            $table->json('meta')->nullable();
            $table->integer('stock')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('product_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreignId('child_id')->references('id')->on('products')->cascadeOnDelete();
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
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_related');
    }
}
