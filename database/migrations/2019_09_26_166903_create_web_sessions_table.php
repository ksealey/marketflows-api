<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWebSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('web_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 128)->index(); // Public(Web) ID
            $table->bigInteger('web_profile_identity_id')->unsigned();
            $table->bigInteger('web_device_id')->unsigned();
            $table->bigInteger('campaign_id')->unsigned()->nullable();
            $table->bigInteger('phone_number_id')->unsigned()->nullable();
            $table->string('ip', 32)->nullable();
            $table->timestamps();
            $table->foreign('web_profile_identity_id')->references('id')->on('web_profile_identities');
            $table->foreign('web_device_id')->references('id')->on('web_devices');
            $table->foreign('campaign_id')->references('id')->on('campaigns');
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
        Schema::dropIfExists('sessions');
    }
}
