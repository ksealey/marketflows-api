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
            $table->bigInteger('role_id')->unsigned()->nullable();
            $table->string('timezone', 64);
            $table->string('first_name', 32);
            $table->string('last_name', 32);
            $table->string('email', 128)->unique();
            $table->string('phone', 16)->nullable();
            $table->string('password_hash', 128);
            $table->string('auth_token', 128);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('password_reset_at')->nullable();
            $table->dateTime('disabled_until')->nullable();
            $table->integer('login_attempts')->unsigned()->default(0);
            $table->dateTime('email_alerts_enabled_at')->nullable();
            $table->dateTime('sms_alerts_enabled_at')->nullable();
            $table->dateTime('last_heartbeat_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('role_id')->references('id')->on('roles');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
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
