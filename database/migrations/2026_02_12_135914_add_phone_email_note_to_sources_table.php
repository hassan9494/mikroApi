<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneEmailNoteToSourcesTable extends Migration
{
    public function up()
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('order');
            $table->string('email')->nullable()->after('phone');
            $table->text('note')->nullable()->after('email');
        });
    }

    public function down()
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email', 'note']);
        });
    }
}
