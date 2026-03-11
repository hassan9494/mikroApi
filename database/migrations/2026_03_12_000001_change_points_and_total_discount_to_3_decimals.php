<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('points_discount', 10, 3)->default(0)->change();
            $table->decimal('total_discount', 10, 3)->default(0)->change();
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->decimal('points_discount', 10, 3)->default(0)->change();
            $table->decimal('total_discount', 10, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('points_discount', 10, 2)->default(0)->change();
            $table->decimal('total_discount', 10, 2)->default(0)->change();
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->decimal('points_discount', 10, 2)->default(0)->change();
            $table->decimal('total_discount', 10, 2)->default(0)->change();
        });
    }
};
