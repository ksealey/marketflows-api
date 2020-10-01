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
            'blocked_phone_numbers.id',
            'blocked_phone_numbers.country_code',
            'blocked_phone_numbers.number',
            'phone_numbers.id',
            'phone_numbers.country_code',
            'phone_numbers.number',
            'phone_numbers.name',
            'blocked_calls.id',
            'blocked_calls.blocked_phone_number_id',
            'blocked_calls.created_at',
            'companies.id',
            'companies.name',
        ];
    }

    static public function exports() : array
    {
        return [
            'id'                                    => 'Id',
            'company_name'                          => 'Company',
            'blocked_phone_number_name'             => 'Blocked Number Name',
            'blocked_phone_number_country_code'     => 'Blocked Country Code',
            'blocked_phone_number_number'           => 'Blocked Phone Number',
            'phone_number_name'                     => 'Dialed Number Name',
            'phone_number_country_code'             => 'Dialed Country Code',
            'phone_number_number'                   => 'Dialed Number',
            'created_at_local'                      => 'Created'
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
                    'blocked_phone_numbers.name AS blocked_phone_number_name',
                    'blocked_phone_numbers.country_code AS blocked_phone_number_country_code',
                    'blocked_phone_numbers.number AS blocked_phone_number_number',
                    'phone_numbers.number AS phone_number_number', 
                    'phone_numbers.country_code AS phone_number_country_code', 
                    'phone_numbers.name AS phone_number_name',
                    'phone_numbers.company_id AS company_id',
                    'phone_numbers.deleted_at AS phone_number_deleted_at',
                    'companies.name AS company_name',
                    
                    DB::raw("DATE_FORMAT(CONVERT_TZ(blocked_calls.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local") 
                ])
                ->where('blocked_calls.account_id', $input['account_id'])
                ->leftJoin('phone_numbers', 'phone_numbers.id', 'blocked_calls.phone_number_id')
                ->leftJoin('companies', 'companies.id', 'phone_numbers.company_id')
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
