<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class BlockedCall extends Model
{
    use SoftDeletes;
    
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'account_id',
        'blocked_phone_number_id',
        'phone_number_id',
        'created_at'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    public static function accessibleFields()
    {
        return [
            'phone_numbers.number',
            'phone_numbers.name',
            'blocked_phone_numbers.number',
            'blocked_phone_numbers.country_code',
            'blocked_calls.id',
            'blocked_calls.phone_number_id',
            'blocked_calls.blocked_phone_number_id',
            'blocked_calls.created_at'
        ];
    }

    static public function exports() : array
    {
        return [
            'phone_number_name'             => 'Dialed Number Name',
            'phone_number'                  => 'Dialed Number',
            'blocked_phone_number_number'   => 'Blocked Country Code',
            'blocked_phone_number_number'   => 'Blocked Phone Number',
            'created_at_local'              => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Blocked Calls';
    }

    static public function exportQuery($user, array $input)
    {
        return BlockedCall::select([
                                'blocked_calls.*', 
                                'phone_numbers.number AS phone_number', 
                                'phone_numbers.country_code AS phone_number_country_code', 
                                'phone_numbers.name AS phone_number_name',
                                'blocked_phone_numbers.number AS blocked_phone_number_number',
                                'blocked_phone_numbers.country_code AS blocked_phone_number_country_code',
                                DB::raw("DATE_FORMAT(CONVERT_TZ(companies.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local") 
                            ])
                            ->where('blocked_calls.account_id', $input['account_id'])
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'blocked_calls.phone_number_id')
                            ->leftJoin('blocked_phone_numbers', 'blocked_phone_numbers.id', 'blocked_calls.blocked_phone_number_id');
    }


    /**
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return null; // Yes, return nothing
    }

    /**
     * Get the kind
     * 
     */
    public function getKindAttribute()
    {
        return 'BlockedCall';
    }
}
