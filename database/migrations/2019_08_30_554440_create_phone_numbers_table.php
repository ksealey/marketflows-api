<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhoneNumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 128)->index();
            $table->string('external_id', 64)->index();
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('phone_number_config_id')->unsigned();
            $table->bigInteger('phone_number_pool_id')->unsigned()->nullable();
            $table->bigInteger('phone_number_pool_provision_rule_id')->unsigned()->nullable();
            $table->string('category', 64);
            $table->string('sub_category', 64);
            $table->string('country_code', 8)->nullable();
            $table->string('number', 16)->index();
            $table->boolean('voice')->default(0);
            $table->boolean('sms')->default(0);
            $table->boolean('mms')->default(0);
            $table->string('name', 255);
            $table->bigInteger('campaign_id')->unsigned()->nullable();
            $table->dateTime('assigned_at', 6)->nullable();
            $table->dateTime('last_assigned_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
            $table->foreign('phone_number_pool_provision_rule_id')->references('id')->on('phone_number_pool_provision_rules');
            $table->foreign('campaign_id')->references('id')->on('campaigns');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_numbers');
    }
}
