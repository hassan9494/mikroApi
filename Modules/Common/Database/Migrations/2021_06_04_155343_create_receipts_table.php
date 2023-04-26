<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('course_student_id')->nullable();
            $table->unsignedInteger('graduation_project_id')->nullable();
            $table->string('name');
            $table->double('amount');
            $table->timestamp('date')->nullable();
            $table->longText('explanation')->nullable();
            $table->longText('notes')->nullable();
            $table->enum('type', ['CASH', 'CHECK']);
            $table->string('check_number')->nullable();
            $table->boolean('taxed')->default(false);
            $table->integer('tax_number')->nullable();
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
        Schema::dropIfExists('receipts');
    }
}
