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
            $table->string('plan', 32);
            $table->decimal('balance', 12, 2);
            $table->dateTime('auto_reload_enabled_at')->nullable();
            $table->integer('auto_reload_minimum')->nullable()->unsigned();
            $table->integer('auto_reload_amount')->nullable()->unsigned();
            $table->string('stripe_id', 64)->nullable();
            $table->dateTime('disabled_at')->nullable();
            $table->dateTime('last_billed_at')->nullable();
            $table->dateTime('bill_at');
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
