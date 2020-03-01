<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();  
            $table->uuid('persisted_id')->index(); 
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned()->nullable();
            $table->boolean('first_session');
            $table->string('host', 128);
            $table->string('ip', 32);
            $table->integer('device_width')->unsigned();
            $table->integer('device_height')->unsigned();
            $table->string('device_type', 64)->nullable();
            $table->string('device_brand', 64)->nullable();
            $table->string('device_os', 64)->nullable();
            $table->string('browser_type', 64)->nullable();
            $table->string('browser_version', 64)->nullable();
            $table->string('token', 40);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('ended_at')->nullable();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sessions');
    }
}
