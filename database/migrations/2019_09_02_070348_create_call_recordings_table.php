<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCallRecordingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('call_id')->unsigned();
            $table->string('external_id', 64)->index();
            $table->integer('duration')->unsigned();
            $table->string('path', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('call_recordings');
    }
}
