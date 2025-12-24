<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixTransferOrderStatus extends Migration
{
    public function up()
    {
        // First update any CANCELED status to PENDING
        DB::table('transfer_orders')
            ->where('status', 'CANCELED')
            ->update(['status' => 'PENDING']);

        // Change the ENUM to only PENDING and COMPLETED
        DB::statement("ALTER TABLE transfer_orders MODIFY COLUMN status ENUM('PENDING', 'COMPLETED') NOT NULL DEFAULT 'PENDING'");
    }

    public function down()
    {
        // Revert back to original ENUM if needed
        DB::statement("ALTER TABLE transfer_orders MODIFY COLUMN status ENUM('PENDING', 'COMPLETED', 'CANCELED') NOT NULL DEFAULT 'PENDING'");
    }
}
