<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOutlaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('outlays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('amount');
            $table->timestamp('date')->nullable();
            $table->string('invoice')->nullable();
            $table->longText('notes')->nullable();
            $table->enum('type', ['PURCHASE', 'OUTLAY']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outlays');
    }
}
