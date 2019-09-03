<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhoneNumberPoolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_number_pools', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->string('name', 255)->nullable();
            $table->string('source', 255)->nullable();
            $table->string('forward_to_country_code', 16)->nullable();
            $table->string('forward_to_number', 16)->nullable();
            $table->bigInteger('audio_clip_id')->unsigned()->nullable();
            $table->dateTime('recording_enabled_at')->nullable();
            $table->dateTime('auto_provision_enabled_at')->nullable();
            $table->string('whisper_message', 255)->nullable();
            $table->string('whisper_language', 32)->nullable();
            $table->string('whisper_voice', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('audio_clip_id')->references('id')->on('audio_clips');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_number_pools');
    }
}
