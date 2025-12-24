<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixTransferOrderHistoryValues extends Migration
{
    public function up()
    {
        // Update status change history to have proper change_type
        DB::table('transfer_order_histories')
            ->where('action', 'status_changed')
            ->whereNull('change_type')
            ->update(['change_type' => 'status']);

        // Update notes change history
        DB::table('transfer_order_histories')
            ->where('field', 'notes')
            ->whereNull('change_type')
            ->update(['change_type' => 'notes']);

        // Update product-related history
        DB::table('transfer_order_histories')
            ->whereIn('field', ['quantity', 'from_location', 'to_location', 'product'])
            ->whereNull('change_type')
            ->update(['change_type' => DB::raw('field')]);

        // For general updates without field
        DB::table('transfer_order_histories')
            ->where('action', 'updated')
            ->whereNull('field')
            ->whereNull('change_type')
            ->update(['change_type' => 'general']);
    }

    public function down()
    {
        // No need to rollback
    }
}
