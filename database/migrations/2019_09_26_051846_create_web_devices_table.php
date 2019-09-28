<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWebDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('web_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 128)->index();
            $table->bigInteger('web_profile_id')->unsigned();
            $table->string('fingerprint', 128)->index();
            $table->string('ip', 32)->index();
            $table->integer('width')->unsigned()->nullable();
            $table->integer('height')->unsigned()->nullable();
            $table->string('type', 128)->nullable()->index();
            $table->string('brand', 128)->nullable();
            $table->string('os', 128)->nullable()->index();
            $table->string('os_version', 128)->nullable();
            $table->string('browser', 128)->nullable()->index();
            $table->string('browser_version', 128)->nullable();
            $table->string('browser_engine', 128)->nullable();
            $table->timestamps();
            $table->foreign('web_profile_id')->references('id')->on('web_profiles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('devices');
    }
}
