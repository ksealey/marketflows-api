<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 64);
            $table->string('tts_voice', 32);
            $table->string('tts_language', 32);
            $table->string('source_param', 128);
            $table->string('medium_param', 128);
            $table->string('content_param', 128);
            $table->string('campaign_param', 128);
            $table->string('keyword_param', 128);
            $table->boolean('source_referrer_when_empty')->default(1);
            $table->dateTime('suspended_at')->nullable();
            $table->string('suspension_message', 255)->nullable();
            $table->string('suspension_code', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
        });
    }
            
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
}
