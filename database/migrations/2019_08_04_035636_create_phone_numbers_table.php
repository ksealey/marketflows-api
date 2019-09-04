<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhoneNumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->string('external_id', 64);
            $table->bigInteger('phone_number_pool_id')->unsigned()->nullable();
            $table->string('country_code', 16)->nullable();
            $table->string('number', 16)->index();
            $table->boolean('voice')->default(0);
            $table->boolean('sms')->default(0);
            $table->boolean('mms')->default(0);
            $table->string('name', 255);
            $table->string('source', 255);
            $table->string('forward_to_country_code', 16)->nullable();
            $table->string('forward_to_number', 16);
            $table->bigInteger('audio_clip_id')->unsigned()->nullable();
            $table->dateTime('recording_enabled_at')->nullable();
            $table->string('whisper_message', 255)->nullable();
            $table->string('whisper_language', 32)->nullable();
            $table->string('whisper_voice', 64)->nullable();
            $table->string('assigned_session_id', 255)->nullable()->index();
            $table->dateTime('last_assigned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
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
        Schema::dropIfExists('phone_numbers');
    }
}
