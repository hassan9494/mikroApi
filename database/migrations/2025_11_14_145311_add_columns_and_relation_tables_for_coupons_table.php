<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsAndRelationTablesForCouponsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->integer('count_per_user')->nullable()->after('count');
            $table->integer('main_count')->nullable()->after('count_per_user');
            $table->integer('use_count')->nullable()->after('main_count');
            $table->boolean('apply_count')->default(false)->after('count_per_user');
            $table->boolean('apply_count_per_user')->default(false)->after('apply_count');
        });
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->references('id')->on('coupons')->onDelete('CASCADE');
            $table->foreignId('product_id')->references('id')->on('products');
            $table->string('product_name')->default(0);
            $table->timestamps();
        });
        Schema::create('coupon_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->references('id')->on('coupons')->onDelete('CASCADE');
            $table->foreignId('brand_id')->references('id')->on('brands');
            $table->string('brand_name')->default(0);
            $table->timestamps();
        });
        Schema::create('coupon_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->references('id')->on('coupons')->onDelete('CASCADE');
            $table->foreignId('user_id')->references('id')->on('users');
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
        //
    }
}
