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
            $table->bigInteger('company_id')->unsigned()->nullable();
            $table->bigInteger('role_id')->unsigned()->nullable();
            $table->boolean('is_admin')->default(0);
            $table->boolean('is_client')->default(0);
            $table->string('first_name', 64);
            $table->string('last_name', 64);
            $table->string('email', 255)->unique();
            $table->string('country_code', 8)->nullable();
            $table->string('area_code', 8);
            $table->string('phone', 16);
            $table->string('password_hash', 255);
            $table->string('auth_token', 255);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->string('timezone', 255);
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('password_reset_at')->nullable();
            $table->dateTime('disabled_until')->nullable();
            $table->integer('login_attempts')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('role_id')->references('id')->on('roles');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
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
