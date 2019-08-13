<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCampaignPhoneNumberPoolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('campaign_phone_number_pools', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('campaign_id')->unsigned();
            $table->bigInteger('phone_number_pool_id')->unsigned();
            $table->timestamps();
            $table->foreign('campaign_id')->references('id')->on('campaigns');
            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('campaign_phone_number_pools');
    }
}
