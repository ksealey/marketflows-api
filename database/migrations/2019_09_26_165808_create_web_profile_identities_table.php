<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWebProfileIdentitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('web_profile_identities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 128)->index();
            $table->bigInteger('web_profile_id')->unsigned();
            $table->string('domain', 255);
            $table->string('external_id', 128)->nullable()->index();
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('email', 255)->nullable()->index();
            $table->string('home_phone', 16)->nullable();
            $table->string('mobile_phone', 16)->nullable();
            $table->string('company', 255)->nullable();
            $table->string('address_street', 255)->nullable();
            $table->string('address_street_2', 255)->nullable();
            $table->string('address_city', 255)->nullable()->index();
            $table->string('address_state', 255)->nullable()->index();
            $table->string('address_postcode', 16)->nullable()->index();
            $table->string('address_country', 255)->nullable()->index();
            $table->decimal('location_lat', 16, 8)->nullable()->index();
            $table->decimal('location_lng', 16, 8)->nullable()->index();
            $table->timestamps();
            $table->foreign('web_profile_id')->references('id')->on('web_profiles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('web_profile_identities');
    }
}
