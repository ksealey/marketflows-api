<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduledExportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scheduled_exports', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('report_id')->unsigned();
            $table->smallInteger('day_of_week')->unsigned()->index();
            $table->smallInteger('hour_of_day')->unsigned()->index();
            $table->string('delivery_method', 32);
            $table->string('delivery_email_addresses', 1024)->nullable();
            $table->dateTime('last_ran_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
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
        Schema::dropIfExists('scheduled_exports');
    }
}
