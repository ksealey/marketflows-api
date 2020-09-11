<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactCallCountView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS contact_call_count");
        DB::statement("CREATE VIEW contact_call_count AS
                        SELECT id as contact_id, 
                        (SELECT COUNT(*) FROM calls WHERE contact_id = contacts.id) as call_count
                        FROM contacts
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS contact_call_count");
    }
}
