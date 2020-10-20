<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePhoneNumberCallCountView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS phone_number_call_count");
        DB::statement("CREATE VIEW phone_number_call_count AS
                        SELECT id as phone_number_id, 
                            (SELECT COUNT(*) FROM calls WHERE phone_number_id = phone_numbers.id AND deleted_at IS NULL) as call_count
                        FROM phone_numbers
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
        DB::statement("DROP VIEW IF EXISTS phone_number_call_count");
    }
}
