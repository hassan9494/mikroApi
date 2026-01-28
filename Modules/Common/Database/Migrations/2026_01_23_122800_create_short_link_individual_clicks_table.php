<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShortLinkIndividualClicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('short_link_individual_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('click_summary_id')->constrained('short_link_click_summaries')->onDelete('cascade');
            $table->timestamp('clicked_at')->useCurrent();

            $table->index(['click_summary_id']);
            $table->index(['clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('short_link_individual_clicks');
    }
}
