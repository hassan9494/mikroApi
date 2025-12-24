<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixTransferOrderHistoryData extends Migration
{
    public function up()
    {
        // If needed, fix existing data
        DB::statement("UPDATE transfer_order_histories
                       SET change_type = CASE
                           WHEN field = 'status' THEN 'status'
                           WHEN field = 'quantity' THEN 'quantity'
                           WHEN field = 'from_location' THEN 'from_location'
                           WHEN field = 'to_location' THEN 'to_location'
                           WHEN field = 'product' THEN 'product'
                           ELSE NULL
                       END
                       WHERE change_type IS NULL");
    }

    public function down()
    {
        // No rollback needed
    }
}
