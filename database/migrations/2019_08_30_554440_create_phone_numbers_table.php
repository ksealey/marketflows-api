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
            $table->string('category', 64);
            $table->string('sub_category', 64);
            $table->string('country_code', 8)->nullable();
            $table->string('number', 16)->index();
            $table->boolean('voice')->default(0);
            $table->boolean('sms')->default(0);
            $table->boolean('mms')->default(0);
            $table->boolean('toll_free');
            $table->string('name', 255)->index();
            $table->string('source', 255);
            $table->json('swap_rules')->nullable();
            $table->dateTime('assigned_at', 6)->nullable();
            $table->dateTime('last_assigned_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

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
