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
            $table->string('type', 16);
            $table->string('category', 32)->nullable();
            $table->string('sub_category', 32)->nullable();

            $table->bigInteger('phone_number_pool_id')->unsigned()->nullable();
            $table->uuid('session_id')->nullable();

            $table->string('external_id', 64)->unique();
            $table->string('direction', 16);
            $table->string('status', 64);

            $table->string('caller_name', 64)->nullable();
            $table->string('caller_country_code', 8)->nullable();
            $table->string('caller_number', 16)->index();
            $table->string('caller_city', 64)->index()->nullable();
            $table->string('caller_state', 64)->index()->nullable();
            $table->string('caller_zip', 16)->index()->nullable();
            $table->string('caller_country', 64)->nullable();

            $table->string('source', 128)->nullable();
            $table->string('medium', 128)->nullable();
            $table->string('content', 128)->nullable();
            $table->string('campaign', 128)->nullable();

            $table->boolean('recording_enabled');
            $table->boolean('caller_id_enabled');
            $table->string('forwarded_to', 24);

            $table->integer('duration')->unsigned()->nullable();
            $table->decimal('cost', 8, 4)->nullable();
            
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
            $table->foreign('phone_number_pool_id')->references('id')->on('phone_number_pools');
            $table->foreign('session_id')->references('id')->on('sessions');
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
