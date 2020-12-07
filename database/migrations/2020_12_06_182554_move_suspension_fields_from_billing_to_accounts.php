<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MoveSuspensionFieldsFromBillingToAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //  Add columns to accounts
        Schema::table('accounts', function (Blueprint $table) {
            $table->dateTime('next_suspension_warning_at')->nullable()->after('keyword_param');
            $table->smallInteger('suspension_warnings')->unsigned()->default(0)->after('keyword_param');
        });

        //  Move data from billing
        DB::table('accounts')->update([
            'next_suspension_warning_at' => DB::raw('(SELECT next_suspension_warning_at from billing where account_id = accounts.id)'),
            'suspension_warnings'        => DB::raw('(SELECT suspension_warnings from billing where account_id = accounts.id)'),
        ]);

        //  Drop columns from billing
        Schema::table('billing', function(Blueprint $table){
            $table->dropColumn('next_suspension_warning_at');
            $table->dropColumn('suspension_warnings');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('billing_to_accounts', function (Blueprint $table) {
            //
        });
    }
}
