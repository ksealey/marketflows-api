<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKeypadConversionsToPhoneNumberConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('phone_number_configs', function (Blueprint $table) {
            $table->dropForeign('phone_number_configs_keypress_audio_clip_id_foreign');
            $table->dropColumn('keypress_audio_clip_id');
            $table->dropColumn('keypress_message_type');
            
            $table->string('keypress_directions_message',255)->nullable()->after('keypress_message');
            $table->string('keypress_error_message', 255)->nullable()->after('keypress_directions_message');
            $table->string('keypress_success_message', 255)->nullable()->after('keypress_error_message');
            $table->string('keypress_failure_message', 255)->nullable()->after('keypress_success_message');


            $table->boolean('keypress_conversion_enabled')->default(0)->after('whisper_message');
            $table->tinyInteger('keypress_conversion_key_converted')->nullable()->after('keypress_conversion_enabled');
            $table->tinyInteger('keypress_conversion_key_unconverted')->nullable()->after('keypress_conversion_key_converted');
            $table->tinyInteger('keypress_conversion_attempts')->nullable()->after('keypress_conversion_key_unconverted');
            $table->tinyInteger('keypress_conversion_timeout')->nullable()->after('keypress_conversion_attempts');
            $table->string('keypress_conversion_directions_message', 255)->nullable()->after('keypress_conversion_timeout');
            $table->string('keypress_conversion_error_message', 255)->nullable()->after('keypress_conversion_directions_message');
            $table->string('keypress_conversion_success_message', 255)->nullable()->after('keypress_conversion_error_message');
            $table->string('keypress_conversion_failure_message', 255)->nullable()->after('keypress_conversion_success_message');

            $table->boolean('keypress_qualification_enabled')->default(0)->after('keypress_conversion_failure_message');
            $table->tinyInteger('keypress_qualification_key_qualified')->nullable()->after('keypress_qualification_enabled');
            $table->tinyInteger('keypress_qualification_key_potential')->nullable()->after('keypress_qualification_key_qualified');
            $table->tinyInteger('keypress_qualification_key_customer')->nullable()->after('keypress_qualification_key_potential');
            $table->tinyInteger('keypress_qualification_key_unqualified')->nullable()->after('keypress_qualification_key_customer');
            $table->tinyInteger('keypress_qualification_attempts')->nullable()->after('keypress_qualification_key_unqualified');
            $table->tinyInteger('keypress_qualification_timeout')->nullable()->after('keypress_qualification_attempts');
            $table->string('keypress_qualification_directions_message', 255)->nullable()->after('keypress_qualification_timeout');
            $table->string('keypress_qualification_error_message', 255)->nullable()->after('keypress_qualification_directions_message');
            $table->string('keypress_qualification_success_message', 255)->nullable()->after('keypress_qualification_error_message');
            $table->string('keypress_qualification_failure_message', 255)->nullable()->after('keypress_qualification_success_message');

        });

        //  Move data to new field
        DB::table('phone_number_configs')->update([
            'keypress_directions_message' => DB::raw('keypress_message'),
        ]);

        Schema::table('phone_number_configs', function (Blueprint $table) {
            $table->dropColumn('keypress_message');
        });
        
        Schema::table('calls', function(Blueprint $table){
            $table->string('lead_status', 16)->nullable()->default('Unknown')->after('converted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('phone_number_configs', function (Blueprint $table) {
            //
        });
    }
}
