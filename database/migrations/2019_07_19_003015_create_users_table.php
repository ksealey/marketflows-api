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
            $table->bigInteger('company_id')->unsigned();
            $table->string('timezone', 255);
            $table->string('first_name', 64);
            $table->string('last_name', 64);
            $table->string('email', 255)->unique();
            $table->string('country_code', 8)->nullable();
            $table->string('area_code', 8);
            $table->string('phone', 16);
            $table->string('password_hash', 255);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('password_reset_at')->nullable();
            $table->dateTime('disabled_until')->nullable();
            $table->integer('login_attempts')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies');
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
