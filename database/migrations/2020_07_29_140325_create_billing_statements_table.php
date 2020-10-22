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
            $table->smallInteger('payment_attempts')->unsigned()->default(0);
            $table->dateTime('next_payment_attempt_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->string('intent_id', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('billing_id')->references('id')->on('billing');
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
