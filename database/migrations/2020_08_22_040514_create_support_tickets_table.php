<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupportTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned();
            $table->string('subject', 255);
            $table->string('description', 1024);
            $table->string('urgency', 32);
            $table->bigInteger('created_by_user_id')->unsigned()->nullable();
            $table->bigInteger('created_by_agent_id')->unsigned()->nullable();
            $table->bigInteger('agent_id')->unsigned()->nullable();
            $table->string('status', 32);
            $table->dateTime('closed_at')->nullable();
            $table->string('closed_by', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreign('created_by_agent_id')->references('id')->on('agents');
            $table->foreign('agent_id')->references('id')->on('agents');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('support_tickets');
    }
}
