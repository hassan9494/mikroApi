<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTaxExemptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tax_exempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->enum('identity_number_type', [
                'NIN', 'PN', 'TN'
            ])->default('NIN');
            $table->string('identity_number')->nullable();
            $table->boolean('tax_exempt')->default(true);
            $table->boolean('tax_zero')->default(false);
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
        Schema::dropIfExists('tax_exempts');
    }
}
