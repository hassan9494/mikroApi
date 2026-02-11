<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEditableColumnsToStockAdjustmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->enum('previous_status', ['pending', 'approved', 'rejected'])->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('previous_status');

            // Add index for better performance
            $table->index(['status', 'previous_status']);
            $table->index('status_changed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn(['previous_status', 'status_changed_at']);
            $table->dropIndex(['status', 'previous_status']);
            $table->dropIndex(['status_changed_at']);
        });
    }
}
