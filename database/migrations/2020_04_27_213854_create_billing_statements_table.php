<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBillingStatementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billing_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->date('billing_period_starts_at');
            $table->date('billing_period_ends_at');
            $table->bigInteger('payment_method_id')->unsigned()->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('charge_id', 64)->nullable();
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
