<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShelfCapacityColumnsToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_capacity')->default(0)->after('store_location');
            $table->integer('max_capacity')->default(0)->after('min_capacity');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_capacity', 'max_capacity']);
        });
    }
}
