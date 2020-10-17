<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->unsigned();
            $table->bigInteger('company_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned();
            $table->bigInteger('contact_id')->unsigned();

            $table->string('phone_number_name', 64);
            $table->string('type', 16);
            $table->string('category', 32)->nullable();
            $table->string('sub_category', 32)->nullable();

            $table->bigInteger('keyword_tracking_pool_id')->unsigned()->nullable();
            $table->string('keyword_tracking_pool_name', 64)->nullable();
            $table->bigInteger('keyword_tracking_pool_session_id')->unsigned()->nullable();

            $table->string('external_id', 64)->unique();
            $table->string('direction', 16);
            $table->string('status', 64);

            $table->string('source', 512)->nullable();
            $table->string('medium', 128)->nullable();
            $table->string('content', 128)->nullable();
            $table->string('campaign', 128)->nullable();
            $table->string('keyword', 128)->nullable();
            $table->boolean('is_organic')->nullable();
            $table->boolean('is_paid')->nullable();
            $table->boolean('is_direct')->nullable();
            $table->boolean('is_referral')->nullable();
            $table->boolean('is_search')->nullable();
            $table->boolean('recording_enabled');
            $table->boolean('transcription_enabled');
            
            $table->string('forwarded_to', 24);

            $table->integer('duration')->unsigned()->nullable();
            
            $table->boolean('first_call')->default(0);
            
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('keyword_tracking_pool_id')->references('id')->on('keyword_tracking_pools');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->index(['created_at', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calls');
    }
}
