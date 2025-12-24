<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop the existing table
        Schema::dropIfExists('transfer_order_histories');

        // Create new table with proper structure
        Schema::create('transfer_order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('transfer_order_product_id')->nullable();
            $table->string('action');
            $table->string('field')->nullable();
            $table->string('change_type', 50)->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('transfer_order_id')
                ->references('id')
                ->on('transfer_orders')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            $table->foreign('transfer_order_product_id')
                ->references('id')
                ->on('transfer_order_products')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['transfer_order_id', 'created_at']);
            $table->index(['action', 'change_type']);
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transfer_order_histories');

        // Recreate original structure
        Schema::create('transfer_order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('transfer_order_id')
                ->references('id')
                ->on('transfer_orders')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['transfer_order_id', 'created_at']);
            $table->index('action');
        });
    }
};
