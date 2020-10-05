<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhoneNumberConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_number_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->bigInteger('company_id')->unsigned();
            $table->string('name', 64);
            $table->string('forward_to_number', 16);

            $table->boolean('greeting_enabled')->default(0);
            $table->string('greeting_message_type', 16)->nullable();
            $table->string('greeting_message', 255)->nullable();
            $table->bigInteger('greeting_audio_clip_id')->unsigned()->nullable();
            
            $table->boolean('keypress_enabled')->default(0);
            $table->tinyInteger('keypress_key')->unsigned()->nullable();
            $table->tinyInteger('keypress_attempts')->unsigned()->nullable();
            $table->tinyInteger('keypress_timeout')->unsigned()->nullable();
            $table->string('keypress_message_type', 16)->nullable();
            $table->string('keypress_message', 255)->nullable();
            $table->bigInteger('keypress_audio_clip_id')->unsigned()->nullable();

            $table->boolean('whisper_enabled')->default(0);
            $table->string('whisper_message', 255)->nullable();

            $table->boolean('recording_enabled')->default(0);

            $table->boolean('transcription_enabled')->default(0);

            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('greeting_audio_clip_id')->references('id')->on('audio_clips');
            $table->foreign('keypress_audio_clip_id')->references('id')->on('audio_clips');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_number_configs');
    }
}
