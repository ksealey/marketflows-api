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
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('phone_number_pool_id')->unsigned()->nullable();
            $table->bigInteger('phone_number_config_id')->unsigned();
            $table->string('name', 255)->index();
            $table->string('category', 64);
            $table->string('sub_category', 64);
            $table->string('country_code', 8)->nullable();
            $table->string('number', 16)->index();
            $table->boolean('voice')->default(0);
            $table->boolean('sms')->default(0);
            $table->boolean('mms')->default(0);
            $table->boolean('toll_free');
            $table->string('source', 255)->nullable();
            $table->json('swap_rules')->nullable();
            $table->bigInteger('assignments')->unsigned()->default(0);
            $table->dateTime('last_assigned_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
            $table->foreign('phone_number_config_id')->references('id')->on('phone_number_configs');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('created_by')->references('id')->on('users');
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
