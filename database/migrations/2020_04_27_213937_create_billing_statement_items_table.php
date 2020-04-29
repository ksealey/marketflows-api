<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBillingStatementItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billing_statement_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('billing_statement_id')->unsigned();
            $table->string('label', 64);
            $table->string('details', 128);
            $table->decimal('total', 10, 2);
            $table->foreign('billing_statement_id')->references('id')->on('billing_statements');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('statement_items');
    }
}
