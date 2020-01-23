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
        Schema::connection('events')->create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();  
            $table->uuid('persisted_id')->index(); 
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned()->nullable();
            $table->boolean('first_session');
            $table->string('ip', 32);
            $table->integer('device_width')->unsigned();
            $table->integer('device_height')->unsigned();
            $table->string('device_type', 64)->nullable();
            $table->string('device_brand', 64)->nullable();
            $table->string('device_os', 64)->nullable();
            $table->string('browser_type', 64)->nullable();
            $table->string('browser_version', 64)->nullable();
            $table->string('token', 40);
            $table->timestamps();
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
        Schema::connection('events')->dropIfExists('sessions');
    }
}
