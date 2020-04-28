<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 64);
            $table->string('account_type', 32);
            $table->string('previous_account_type', 32)->nullable();
            $table->dateTime('account_type_updated_at')->nullable();
            $table->string('default_tts_voice', 32);
            $table->string('default_tts_language', 32);
            $table->dateTime('suspended_at')->nullable();
            $table->smallInteger('suspension_code')->unsigned()->nullable();
            $table->string('suspension_message', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
            
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
}
