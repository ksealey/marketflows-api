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
            $table->bigInteger('user_id')->unsigned();
            $table->string('name', 64);
            $table->string('forward_to_country_code', 8)->nullable();
            $table->string('forward_to_number', 16);
            $table->bigInteger('greeting_audio_clip_id')->unsigned()->nullable();
            $table->string('greeting_message', 128)->nullable();
            $table->string('whisper_message', 128)->nullable();
            $table->dateTime('recording_enabled_at')->nullable();
            $table->dateTime('caller_id_enabled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('greeting_audio_clip_id')->references('id')->on('audio_clips');
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
