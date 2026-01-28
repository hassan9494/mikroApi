<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShortLinkClickSummariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('short_link_click_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_link_id')->constrained()->onDelete('cascade');
                $table->string('ip_address', 45);
                $table->string('country')->nullable();
                $table->integer('click_count')->default(1);
                $table->timestamps();

                // Shorter index name to fix the error
                $table->unique(['short_link_id', 'ip_address'], 'link_ip_unique');
                $table->index(['short_link_id']);
                $table->index(['ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('short_link_click_summaries');
    }
}
