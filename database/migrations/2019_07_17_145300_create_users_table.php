<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
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
            $table->dateTime('login_disabled_at')->nullable();
            $table->integer('login_attempts')->unsigned()->default(0);
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
