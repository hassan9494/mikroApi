<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('course_student_id')->nullable();
            $table->unsignedInteger('graduation_project_id')->nullable();
            $table->string('name');
            $table->double('amount');
            $table->timestamp('date')->nullable();
            $table->longText('explanation')->nullable();
            $table->longText('notes')->nullable();
            $table->string('type')->nullable();
            $table->string('check_number')->nullable();
            $table->boolean('taxed')->default(false);
            $table->integer('tax_number')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');

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
        Schema::dropIfExists('project_receipts');
    }
}
