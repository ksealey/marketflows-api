<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRemarketingColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calls', function(Blueprint $table){
            $table->boolean('is_organic')->default(0)->change();
            $table->boolean('is_paid')->default(0)->change();
            $table->boolean('is_direct')->default(0)->change();
            $table->boolean('is_referral')->default(0)->change();
            $table->boolean('is_search')->default(0)->change();
            $table->boolean('is_remarketing')->default(0)->after('is_search');
        });

        Schema::table('phone_numbers', function(Blueprint $table){
            $table->boolean('is_organic')->default(0)->change();
            $table->boolean('is_paid')->default(0)->change();
            $table->boolean('is_direct')->default(0)->change();
            $table->boolean('is_referral')->default(0)->change();
            $table->boolean('is_search')->default(0)->change();
            $table->boolean('is_remarketing')->default(0)->after('is_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
