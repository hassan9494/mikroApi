<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGraduationProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('graduation_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('cost');
            $table->date('deadline');
            $table->longText('description')->nullable();
            $table->longText('students')->nullable();
            $table->longText('notes')->nullable();
            $table->boolean('completed')->default(false);
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
        Schema::dropIfExists('graduation_projects');
    }
}
