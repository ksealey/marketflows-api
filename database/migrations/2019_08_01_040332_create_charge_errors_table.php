<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChargeErrorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('charge_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('payment_method_id')->unsigned();
            $table->decimal('amount', 16, 2);
            $table->string('description', 255);
            $table->string('error', 255);
            $table->text('exception');
            $table->timestamps();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('charge_errors');
    }
}
