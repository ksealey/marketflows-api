<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->id();
            $table->bigInteger('billing_id')->unsigned();
            $table->dateTime('billing_period_starts_at');
            $table->dateTime('billing_period_ends_at');
            $table->bigInteger('payment_id')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('billing_id')->references('id')->on('billing');
            $table->foreign('payment_id')->references('id')->on('payments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('billing_statements');
    }
}
