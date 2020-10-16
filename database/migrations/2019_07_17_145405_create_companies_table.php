<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->string('name', 64);
            $table->string('industry', 64);
            $table->string('country', 32);
            $table->string('tts_voice', 32);
            $table->string('tts_language', 32);
            $table->string('source_param', 128);
            $table->string('medium_param', 128);
            $table->string('content_param', 128);
            $table->string('campaign_param', 128);
            $table->string('keyword_param', 128);
            $table->boolean('source_referrer_when_empty')->default(1);
            $table->string('ga_id', 32)->nullable();
            $table->integer('tracking_expiration_days')->unsigned()->default(30);
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('companies');
    }
}
