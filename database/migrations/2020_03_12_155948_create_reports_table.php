<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable(); // Can be created by system
            $table->string('name', 64);
            $table->string('module', 64);
            $table->string('metric', 32)->nullable();
            $table->string('conditions', 2048)->nullable();
            $table->string('order', 8)->nullable();
            $table->string('date_unit', 32);
            $table->string('date_offsets', 128)->nullable();
            $table->string('date_ranges', 128)->nullable();
            $table->boolean('export_counts_only');
            $table->boolean('export_separate_tabs');
            $table->boolean('is_system_report')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
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
        Schema::dropIfExists('reports');
    }
}
