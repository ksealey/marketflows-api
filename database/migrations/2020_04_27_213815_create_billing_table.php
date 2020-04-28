<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBillingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billing', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->string('stripe_id', 64)->nullable();
            $table->dateTime('bill_at');
            $table->dateTime('last_billed_at')->nullable();
            $table->dateTime('billing_failed_at')->nullable();
            $table->smallInteger('failed_billing_attempts')->unsigned()->default(0);
            $table->string('last_error', 255)->nullable();
            $table->dateTime('locked_at')->nullable();
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
        Schema::dropIfExists('billing');
    }
}
