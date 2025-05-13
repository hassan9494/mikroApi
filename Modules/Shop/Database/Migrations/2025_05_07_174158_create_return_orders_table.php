<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReturnOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('return_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('number');
            $table->timestamp('date')->nullable();
            $table->string('status')->default('DRAFT');
            $table->foreignId('order_id')->references('id')->on('orders');
            $table->string('notes')->nullable();
            $table->float('subtotal')->default(0);
            $table->float('discount_percentage')->default(0);
            $table->float('discount')->default(0);
            $table->float('total')->default(0);
            $table->float('lose')->default(0);
            $table->json('extra_items')->nullable();
            $table->text('qr_code')->nullable();
            $table->foreignId('migrated_by')
                ->nullable()
                ->references('id')
                ->on('users');
            $table->timestamp('migrated_at')->nullable();
            $table->longText('migrate_error')->nullable();
            $table->boolean('is_migrated')->default(0);
            $table->timestamps();
        });
        Schema::create('return_order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_order_id')->references('id')->on('return_orders')->onDelete('CASCADE');
            $table->foreignId('product_id')->references('id')->on('products');
            $table->float('price')->default(0);
            $table->float('discount')->default(0);
            $table->string('name')->default(0);
            $table->integer('quantity')->default(0);
            $table->integer('returned_quantity')->default(0);
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
        Schema::dropIfExists('return_orders');
    }
}
