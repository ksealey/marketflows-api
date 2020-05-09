<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AccountBlockedPhoneNumber\AccountBlockedCall;
use App\Traits\PerformsExport;
use DB;

class AccountBlockedPhoneNumber extends Model
{
    use SoftDeletes, PerformsExport;

    protected $fillable = [
        'account_id',
        'name',
        'number', 
        'country_code',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    static public function exports() : array
    {
        return [
            'id'         => 'Id',
            'name'       => 'Name',
            'number'     => 'Number',
            'call_count' => 'Calls',
            'created_at' => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Account Blocked Numbers';
    }

    static public function exportQuery($user, array $input)
    {
        return AccountBlockedPhoneNumber::select([
                                'account_blocked_phone_numbers.*',
                                DB::raw('(SELECT count(*) FROM account_blocked_calls WHERE account_blocked_calls.account_blocked_phone_number_id = account_blocked_phone_numbers.id) AS call_count')
                          ])
                          ->where('account_blocked_phone_numbers.account_id', $input['account_id']);
    }

    /**
     * Relationships
     * 
     */
    public function getCallCountAttribute()
    {
        return AccountBlockedCall::where('account_blocked_phone_number_id', $this->id)->count();
    }

    /**
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-account-blocked-phone-number', [
            'accountBlockedPhoneNumberId' => $this->id
        ]);
    }

    /**
     * Get the kind
     * 
     */
    public function getKindAttribute()
    {
        return 'AccountBlockedPhoneNumber';
    }

}
