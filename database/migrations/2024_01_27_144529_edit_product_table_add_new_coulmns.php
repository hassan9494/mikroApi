<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EditProductTableAddNewCoulmns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_retired')->default(0);
            $table->string('source')->nullable();
            $table->string('barcode')->nullable();
            $table->string('location')->nullable();
            $table->longText('packageInclude')->nullable();
            $table->integer('listPriority')->default(0);
            $table->integer('maxCartAmount')->default(0);
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
