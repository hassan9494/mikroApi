<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('fax')->nullable()->after('email');
            $table->string('sub')->nullable()->after('fax');
            $table->text('other')->nullable()->after('address');
        });
    }

    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['fax', 'sub', 'other']);
        });
    }
};
