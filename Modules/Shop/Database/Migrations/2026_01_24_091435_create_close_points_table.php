<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClosePointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('close_points', function (Blueprint $table) {
            $table->id();
            $table->dateTime('close_date');
            $table->foreignId('closed_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('close_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('close_points');
    }
}
