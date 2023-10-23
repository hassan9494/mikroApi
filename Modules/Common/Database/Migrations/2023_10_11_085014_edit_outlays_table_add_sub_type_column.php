<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EditOutlaysTableAddSubTypeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outlays', function (Blueprint $table) {
            $table->enum('sub_type', ['TAX', 'NON TAX','ADMINISTRATIVE','GENERAL','OTHER']);
            $table->double('total_amount');
            $table->string('tax')->nullable();
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
