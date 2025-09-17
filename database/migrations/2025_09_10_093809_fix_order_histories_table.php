<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixOrderHistoriesTable extends Migration
{
    public function up()
    {
        // Check if columns exist before adding them
        if (!Schema::hasColumn('order_histories', 'field')) {
            Schema::table('order_histories', function (Blueprint $table) {
                $table->string('field')->nullable()->after('action');
            });
        }

        if (!Schema::hasColumn('order_histories', 'old_value')) {
            Schema::table('order_histories', function (Blueprint $table) {
                $table->text('old_value')->nullable()->after('field');
            });
        }

        if (!Schema::hasColumn('order_histories', 'new_value')) {
            Schema::table('order_histories', function (Blueprint $table) {
                $table->text('new_value')->nullable()->after('old_value');
            });
        }
    }

    public function down()
    {
        // We don't want to remove columns in case they're being used
    }
}
