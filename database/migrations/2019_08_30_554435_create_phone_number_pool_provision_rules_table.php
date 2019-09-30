<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhoneNumberPoolProvisionRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_number_pool_provision_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('phone_number_pool_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->integer('priority')->unsigned();
            $table->string('country', 8);
            $table->string('area_code', 8);
            $table->timestamps();
            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['phone_number_pool_id', 'priority'], 'pool_priority');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_number_pool_provision_rules');
    }
}
