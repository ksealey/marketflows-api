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
            $table->string('name', 255);
            $table->string('plan', 32);
            $table->decimal('balance', 16, 4)->default(0.00);
            $table->dateTime('auto_reload_enabled_at')->nullable();
            $table->integer('auto_reload_minimum')->nullable();
            $table->string('stripe_id', 255)->nullable();
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
