<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Step 1: Add regular indexes
//        Schema::table('products', function (Blueprint $table) {
//            $table->index(['stock', 'min_qty'], 'stock_min_qty_index');
//            $table->index('is_retired', 'is_retired_index');
//        });
//
//        Schema::table('order_products', function (Blueprint $table) {
//            $table->index('product_id', 'product_id_index');
//        });
//
//        // Step 2: Add virtual column with proper data type
//        DB::statement("
//            ALTER TABLE products
//            ADD COLUMN is_kit VARCHAR(10) GENERATED ALWAYS AS (
//                JSON_UNQUOTE(JSON_EXTRACT(options, '$.kit'))
//            ) VIRTUAL
//        ");
//
//        // Step 3: Add index on the virtual column
//        DB::statement("
//            ALTER TABLE products
//            ADD INDEX is_kit_index (is_kit)
//        ");
    }



    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove virtual column first
//        DB::statement("ALTER TABLE products DROP COLUMN is_kit");
//
//        // Remove regular indexes
//        Schema::table('products', function (Blueprint $table) {
//            $table->dropIndex('stock_min_qty_index');
//            $table->dropIndex('is_retired_index');
//        });
//
//        Schema::table('order_products', function (Blueprint $table) {
//            $table->dropIndex('product_id_index');
//        });
    }
}
