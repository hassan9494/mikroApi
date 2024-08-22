<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->timestamp('date')->nullable();
            $table->enum('status', ['DRAFT', 'COMPLETED', 'CANCELED'])->default('DRAFT');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('invoice_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->references('id')->on('invoices')->onDelete('CASCADE');
            $table->foreignId('product_id')->references('id')->on('products');
            $table->float('price')->default(0);
            $table->integer('quantity')->default(0);
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
        Schema::dropIfExists('invoices');
    }
}
