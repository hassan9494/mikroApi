<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalDiscountToOrdersAndProducts extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_discount', 10, 2)->default(0)->after('points_discount');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->decimal('total_discount', 10, 2)->default(0)->after('points_discount');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_discount');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('total_discount');
        });
    }
}
