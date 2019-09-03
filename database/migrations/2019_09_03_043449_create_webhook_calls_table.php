<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWebhookCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->unsigned();
            $table->string('webhook_action_id', 64);
            $table->bigInteger('resource_id')->unsigned()->index();
            $table->string('method', 16);
            $table->string('url', 1024);
            $table->integer('status_code')->unsigned();
            $table->string('error', 255)->nullable();
            $table->timestamps();
            $table->index(['created_at', 'updated_at']);
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
        Schema::dropIfExists('webhook_calls');
    }
}
