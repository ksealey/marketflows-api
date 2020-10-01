<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKeywordTrackingPoolSessionEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('keyword_tracking_pool_session_events', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('keyword_tracking_pool_session_id')->unsigned();
            $table->string('type', 16);
            $table->string('content', 512)->nullable();
            $table->dateTime('created_at');

            $table->foreign('keyword_tracking_pool_session_id', 'ktp_exents_to_session_fk')->references('id')->on('keyword_tracking_pool_sessions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keyword_tracking_pool_session_events');
    }
}
