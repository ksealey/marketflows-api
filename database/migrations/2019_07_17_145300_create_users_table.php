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
            $table->string('timezone', 64);
            $table->string('first_name', 32);
            $table->string('last_name', 32);
            $table->string('email', 128)->unique();
            $table->string('phone', 16)->nullable();
            $table->string('password_hash', 128);
            $table->string('auth_token', 256);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('password_reset_at')->nullable();
            $table->dateTime('disabled_until')->nullable();
            $table->integer('login_attempts')->unsigned()->default(0);
            $table->json('settings');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('account_id')->references('id')->on('accounts');
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
