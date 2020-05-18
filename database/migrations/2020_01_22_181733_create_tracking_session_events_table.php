<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrackingSessionEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tracking_session_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('tracking_session_id')->unsigned();
            $table->string('event_type', 32);
            $table->string('content', 1024)->nullable();
            $table->dateTime('created_at', 6);
            $table->foreign('tracking_session_id')->references('id')->on('tracking_sessions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tracking_session_events');
    }
}
