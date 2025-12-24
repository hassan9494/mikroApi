<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferOrderTables extends Migration
{
    public function up()
    {
        // Create transfer_orders table
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('number')->unique();
            $table->enum('status', ['PENDING', 'COMPLETED', 'CANCELED'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');

            $table->index(['status', 'created_at']);
            $table->index('number');
            $table->index('created_by');
            $table->index('approved_by');
        });

        // Create transfer_order_products table
        Schema::create('transfer_order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('transfer_order_id');
            $table->unsignedBigInteger('product_id');
            $table->enum('from_location', ['stock_available', 'store_available']);
            $table->enum('to_location', ['stock_available', 'store_available']);
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->foreign('transfer_order_id')->references('id')->on('transfer_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');

            $table->index(['transfer_order_id', 'product_id']);
            $table->index('from_location');
            $table->index('to_location');
        });

        // Create transfer_order_histories table
        Schema::create('transfer_order_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('transfer_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('transfer_order_id')->references('id')->on('transfer_orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['transfer_order_id', 'created_at']);
            $table->index('action');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transfer_order_histories');
        Schema::dropIfExists('transfer_order_products');
        Schema::dropIfExists('transfer_orders');
    }
}
