<?php
// database/migrations/2025_09_29_000000_create_task_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('path');
            $table->integer('size');
            $table->string('disk')->default('public');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_attachments');
    }
}
