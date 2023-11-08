<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomsStatementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customs_statements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('amount');
            $table->timestamp('date')->nullable();
            $table->string('invoice')->nullable();
            $table->string('invoice_2_percent')->nullable();
            $table->longText('notes')->nullable();
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
        Schema::dropIfExists('customs_statements');
    }
}
