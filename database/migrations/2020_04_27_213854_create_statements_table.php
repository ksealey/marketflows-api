<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->dateTime('billing_period_starts_at');
            $table->dateTime('billing_period_ends_at');
            $table->bigInteger('payment_method_id')->unsigned();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('statements');
    }
}
