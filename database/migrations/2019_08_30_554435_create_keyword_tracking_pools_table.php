<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKeywordTrackingPoolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('keyword_tracking_pools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->bigInteger('account_id')->unsigned();
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('phone_number_config_id')->unsigned();
            $table->string('name', 64);
            $table->json('swap_rules');
            
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('deleted_by')->unsigned()->nullable(); 
            $table->dateTime('disabled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('phone_number_config_id')->references('id')->on('phone_number_configs');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keyword_tracking_pools');
    }
}
