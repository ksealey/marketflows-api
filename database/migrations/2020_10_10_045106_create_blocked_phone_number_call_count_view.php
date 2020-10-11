<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlockedPhoneNumberCallCountView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS blocked_phone_number_call_count");
        DB::statement("CREATE VIEW blocked_phone_number_call_count AS
                        SELECT id as blocked_phone_number_id, 
                            (SELECT COUNT(*) FROM blocked_calls WHERE blocked_phone_number_id = blocked_phone_numbers.id AND deleted_at IS NULL) as call_count
                        FROM blocked_phone_numbers
                        WHERE deleted_at IS NULL
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS blocked_phone_number_call_count");
    }
}
