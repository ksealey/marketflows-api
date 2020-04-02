<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportAutomationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_automations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('report_id')->unsigned();
            $table->string('type', 32);
            $table->string('email_addresses', 2048)->nullable();
            $table->tinyInteger('day_of_week');
            $table->time('time');
            $table->dateTime('run_at');
            $table->timestamps();
            $table->foreign('report_id')->references('id')->on('reports');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_automations');
    }
}
