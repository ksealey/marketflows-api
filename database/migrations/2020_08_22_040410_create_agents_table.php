<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('role', 32);
            $table->string('timezone', 128);
            $table->string('first_name', 32);
            $table->string('last_name', 32);
            $table->string('email', 128);
            $table->string('phone', 16)->nullable();
            $table->string('password_hash', 128);
            $table->string('password_reset_token', 255)->nullable();
            $table->dateTime('password_reset_expires_at')->nullable();
            $table->string('auth_token', 255);
            $table->dateTime('last_login_at')->nullable();
            $table->boolean('login_disabled')->default(0);
            $table->smallInteger('login_attempts')->unsigned()->default(0);
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agents');
    }
}
