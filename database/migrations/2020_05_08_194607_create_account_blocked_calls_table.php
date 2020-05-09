<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountBlockedCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_blocked_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_blocked_phone_number_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned();
            $table->dateTime('created_at', 6)->index();
            $table->softDeletes();
            
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
            $table->foreign('account_blocked_phone_number_id')->references('id')->on('account_blocked_phone_numbers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_blocked_calls');
    }
}
