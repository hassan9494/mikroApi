<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('user_id');
        });

        Schema::create('stock_count_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('store_available_expected')->default(0);
            $table->integer('store_available_counted')->nullable();
            $table->integer('store_available_difference')->nullable();
            $table->integer('stock_available_expected')->default(0);
            $table->integer('stock_available_counted')->nullable();
            $table->integer('stock_available_difference')->nullable();
            $table->integer('total_expected')->default(0);
            $table->integer('total_counted')->nullable();
            $table->integer('total_difference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('stock_count_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_count_product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_count_id', 'created_at']);
            $table->index('action');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_histories');
        Schema::dropIfExists('stock_count_products');
        Schema::dropIfExists('stock_counts');
    }
};
