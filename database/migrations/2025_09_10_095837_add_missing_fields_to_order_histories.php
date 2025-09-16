<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingFieldsToOrderHistories extends Migration
{
    public function up()
    {
        Schema::table('order_histories', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('order_histories', 'field')) {
                $table->string('field')->nullable()->after('action');
            }

            if (!Schema::hasColumn('order_histories', 'old_value')) {
                $table->text('old_value')->nullable()->after('field');
            }

            if (!Schema::hasColumn('order_histories', 'new_value')) {
                $table->text('new_value')->nullable()->after('old_value');
            }
        });
    }

    public function down()
    {
        Schema::table('order_histories', function (Blueprint $table) {
            // We don't want to remove columns in case they're being used
        });
    }
}
