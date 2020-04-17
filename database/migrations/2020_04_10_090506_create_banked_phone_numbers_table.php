<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBankedPhoneNumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banked_phone_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('external_id', 64)->index();
            $table->string('country', 16);
            $table->string('country_code', 8)->nullable();
            $table->string('number', 16)->index();
            $table->boolean('voice')->default(0);
            $table->boolean('sms')->default(0);
            $table->boolean('mms')->default(0);
            $table->string('type', 16);
            $table->bigInteger('calls')->unsigned()->default(0);
            $table->dateTime('purchased_at');
            $table->dateTime('release_by');
            $table->bigInteger('released_by_account_id')->unsigned();
            $table->string('status', 16);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('released_by_account_id')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('banked_phone_numbers');
    }
}
