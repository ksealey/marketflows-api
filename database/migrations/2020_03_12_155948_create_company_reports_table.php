<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompanyReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('company_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->unsigned();
            $table->string('name', 64);
            $table->string('module', 64);
            $table->string('fields', 256);
            $table->string('metric', 32)->nullable();
            $table->string('metric_order', 8)->nullable();
            $table->string('date_unit', 64);
            $table->string('date_offsets', 256);
            $table->boolean('is_system_report')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_reports');
    }
}
