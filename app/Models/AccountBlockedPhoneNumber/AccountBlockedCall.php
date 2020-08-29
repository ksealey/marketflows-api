<?php

namespace App\Models\AccountBlockedPhoneNumber;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountBlockedCall extends Model
{
    use SoftDeletes;
    
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'account_blocked_phone_number_id',
        'phone_number_id',
        'created_at'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    static public function exports() : array
    {
        return [
            'phone_number_name' => 'Dialed Number Name',
            'phone_number'      => 'Dialed Number',
            'created_at'        => 'Call Date'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Account Blocked Calls';
    }

    static public function exportQuery($user, array $input)
    {
        return AccountBlockedCall::select([
                                'account_blocked_calls.*', 
                                'phone_numbers.number AS phone_number', 
                                'phone_numbers.country_code AS phone_number_country_code', 
                                'phone_numbers.name AS phone_number_name'
                            ])
                            ->where('account_blocked_calls.account_blocked_phone_number_id', $input['account_blocked_phone_number_id'])
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'account_blocked_calls.phone_number_id');
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
        return 'AccountBlockedCall';
    }
}
