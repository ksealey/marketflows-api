<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrackingSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->bigIncrements('id'); 
            $table->uuid('uuid')->index();
            $table->bigInteger('tracking_entity_id')->unsigned(); 
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('phone_number_pool_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned()->nullable();
            $table->string('host', 128);
            $table->string('ip', 64);
            $table->integer('device_width')->unsigned();
            $table->integer('device_height')->unsigned();
            $table->string('device_type', 64)->nullable();
            $table->string('device_brand', 64)->nullable();
            $table->string('device_os', 64)->nullable();
            $table->string('browser_type', 64)->nullable();
            $table->string('browser_version', 64)->nullable();
            $table->string('source', 128)->nullable();
            $table->string('medium', 128)->nullable();
            $table->string('content', 128)->nullable();
            $table->string('campaign', 128)->nullable();
            $table->string('keyword', 128)->nullable();
            $table->string('token', 40);
            $table->boolean('claimed')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('last_heartbeat_at', 6);
            $table->dateTime('ended_at')->nullable();

            $table->foreign('tracking_entity_id')->references('id')->on('tracking_entities');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tracking_sessions');
    }
}
