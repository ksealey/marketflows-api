<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlockedCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blocked_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('blocked_phone_number_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned();
            $table->dateTime('created_at', 6)->index();
            $table->softDeletes();
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
            $table->foreign('blocked_phone_number_id')->references('id')->on('blocked_phone_numbers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocked_calls');
    }
}
