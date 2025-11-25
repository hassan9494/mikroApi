<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixShippingProvidersAutoIncrement extends Migration
{
    public function up()
    {
        // For MySQL
        DB::statement('ALTER TABLE shipping_providers MODIFY id BIGINT UNSIGNED AUTO_INCREMENT');
    }

    public function down()
    {
        DB::statement('ALTER TABLE shipping_providers MODIFY id BIGINT UNSIGNED NOT NULL');
    }
}
