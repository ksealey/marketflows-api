<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->string('external_id', 64);
            $table->integer('last_4')->unsigned();
            $table->date('expiration');
            $table->string('brand', 64);
            $table->string('type', 64);
            $table->boolean('primary_method')->default(0);
            $table->string('error', 128)->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
}
