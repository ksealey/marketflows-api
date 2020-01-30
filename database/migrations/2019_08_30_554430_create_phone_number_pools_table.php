<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhoneNumberPoolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_number_pools', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('phone_number_config_id')->unsigned();
            $table->string('name', 255)->nullable();
            $table->json('referrer_aliases')->nullable();
            $table->json('swap_rules')->nullable();
            $table->boolean('toll_free');
            $table->string('starts_with', 16)->nullable();
            $table->integer('size')->unsigned();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('phone_number_config_id')->references('id')->on('phone_number_configs');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_number_pools');
    }
}
