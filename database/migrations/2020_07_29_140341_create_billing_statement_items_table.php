<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->id();
            $table->bigInteger('billing_statement_id')->unsigned();
            $table->string('label', 64);
            $table->integer('quantity')->unsigned();
            $table->decimal('price', 10, 4)->unsigned();
            $table->decimal('total', 10, 4)->unsigned();
            $table->timestamps();
            $table->softDeletes();

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
        Schema::dropIfExists('billing_statement_items');
    }
}
