<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatementItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('statement_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('statement_id')->unsigned();
            $table->string('label', 128);
            $table->string('description', 255);
            $table->decimal('total', 8, 2);
            $table->timestamps();
            $table->foreign('statement_id')->references('id')->on('statements');
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
