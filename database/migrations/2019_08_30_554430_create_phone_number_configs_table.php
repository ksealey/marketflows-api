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
            $table->bigInteger('company_id')->unsigned();
            $table->string('name', 64);
            $table->string('forward_to_number', 16);
            $table->bigInteger('greeting_audio_clip_id')->unsigned()->nullable();
            $table->string('greeting_message', 128)->nullable();
            $table->string('whisper_message', 128)->nullable();
            $table->tinyInteger('keypress_key')->unsigned()->nullable();
            $table->tinyInteger('keypress_attempts')->unsigned()->nullable();
            $table->tinyInteger('keypress_timeout')->unsigned()->nullable();
            $table->bigInteger('keypress_audio_clip_id')->unsigned()->nullable();
            $table->string('keypress_message', 128)->nullable();
            $table->boolean('recording_enabled')->default(0);
            $table->boolean('keypress_enabled')->default(0);
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

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
