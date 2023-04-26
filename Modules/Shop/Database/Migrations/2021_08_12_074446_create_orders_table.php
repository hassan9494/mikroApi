<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->references('id')
                ->on('users');

            $table->foreignId('shipping_provider_id')
                ->nullable()
                ->references('id')
                ->on('shipping_providers');

            $table->foreignId('city_id')
                ->nullable()
                ->references('id')
                ->on('cities');

            $table->foreignId('coupon_id')
                ->nullable()
                ->references('id')
                ->on('coupons');

            $table->enum('status', [
                'PENDING', 'PROCESSING', 'COMPLETED', 'CANCELED'
            ])->default('PENDING');

            $table->json('customer')->nullable();
            $table->json('shipping')->nullable();
            // taxed: false, exempt: false, dept: false, price_offer
            $table->json('options')->nullable();
            $table->integer('tax_number')->nullable();

            $table->float('subtotal')->default(0);
            $table->float('discount')->default(0);
            $table->float('total')->default(0);
            $table->float('profit')->default(0);


            $table->longText('notes')->nullable();
            $table->longText('invoice_notes')->nullable();

            $table->timestamp('taxed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->json('extra_items')->nullable();

            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->references('id')->on('orders')->onDelete('CASCADE');
            $table->foreignId('product_id')->references('id')->on('products');
            $table->float('price')->default(0);
            $table->float('real_price')->default(0);
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
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_products');
    }
}
