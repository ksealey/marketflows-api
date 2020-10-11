<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupportTicketAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned();
            $table->bigInteger('support_ticket_id')->unsigned();
            $table->bigInteger('support_ticket_comment_id')->unsigned()->nullable();
            $table->string('file_name', 128);
            $table->integer('file_size')->unsigned(); 
            $table->string('file_mime_type', 128);
            $table->string('path', 128);
            $table->bigInteger('created_by_user_id')->unsigned()->nullable();
            $table->bigInteger('created_by_agent_id')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('support_ticket_id')->references('id')->on('support_tickets');
            $table->foreign('support_ticket_comment_id')->references('id')->on('support_ticket_comments');
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreign('created_by_agent_id')->references('id')->on('agents');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('support_ticket_attachments');
    }
}
