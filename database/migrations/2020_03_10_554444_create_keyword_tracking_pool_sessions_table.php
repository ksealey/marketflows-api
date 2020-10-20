<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKeywordTrackingPoolSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('keyword_tracking_pool_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('guuid', 36)->index();
            $table->uuid('uuid')->index();
            $table->bigInteger('keyword_tracking_pool_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned();
            $table->bigInteger('contact_id')->unsigned()->nullable();
            $table->integer('device_width')->unsigned();
            $table->integer('device_height')->unsigned();
            $table->string('device_type', 64);
            $table->string('device_browser', 64);
            $table->string('device_platform', 64);
            $table->string('http_referrer', 1024)->nullable();
            $table->string('landing_url', 1024);
            $table->string('last_url', 1024);
            $table->string('token', 64);
            $table->boolean('active');
            $table->dateTime('last_activity_at', 6)->index();
            $table->dateTime('end_after', 6)->index();
            $table->dateTime('ended_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6)->nullable();
            $table->foreign('keyword_tracking_pool_id', 'ktp_session_to_pool_fk')->references('id')->on('keyword_tracking_pools');
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
            $table->foreign('contact_id')->references('id')->on('contacts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keyword_tracking_pool_sessions');
    }
}
