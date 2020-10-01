<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKeywordTrackingPoolSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('keyword_tracking_pool_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 128)->index();
            $table->bigInteger('keyword_tracking_pool_id')->unsigned();
            $table->bigInteger('phone_number_id')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('keyword_tracking_pool_id', 'ktp_session_to_pool_fk')->references('id')->on('keyword_tracking_pools');
            $table->foreign('phone_number_id')->references('id')->on('phone_numbers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keyword_tracking_pool_sessions');
    }
}
