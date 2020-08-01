<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTranscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transcriptions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('call_id')->unsigned();
            $table->json('text');
            
            $table->foreign('call_id')->references('id')->on('calls');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transcriptions');
    }
}
