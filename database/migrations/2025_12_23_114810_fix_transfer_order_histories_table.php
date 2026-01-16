<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Change columns to TEXT to store JSON properly
        Schema::table('transfer_order_histories', function (Blueprint $table) {
            $table->text('old_value')->nullable()->change();
            $table->text('new_value')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('transfer_order_histories', function (Blueprint $table) {
            $table->json('old_value')->nullable()->change();
            $table->json('new_value')->nullable()->change();
        });
    }
};
