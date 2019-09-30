<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('phone_number_id')->unsigned();
            $table->string('external_id', 64)->index();
            $table->string('direction', 64);
            $table->string('status', 64);
            $table->string('from_country_code', 16)->nullable();
            $table->string('from_number', 16)->index();
            $table->string('from_city', 255)->index()->nullable();
            $table->string('from_state', 255)->index()->nullable();
            $table->string('from_zip', 16)->index()->nullable();
            $table->string('from_country', 255)->nullable();
            $table->string('to_country_code', 16)->nullable();
            $table->string('to_number', 16)->index();
            $table->string('to_city', 255)->index()->nullable();
            $table->string('to_state', 255)->index()->nullable();
            $table->string('to_zip', 16)->index()->nullable();
            $table->string('to_country', 255)->nullable();
            $table->integer('duration')->unsigned()->nullable();
            $table->string('source', 255)->nullable();
            $table->timestamps();
            $table->index(['created_at', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calls');
    }
}
