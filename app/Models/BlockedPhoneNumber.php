<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BlockedCall;
use DB;

class BlockedPhoneNumber extends Model
{
    use SoftDeletes;

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

    static public function accessibleFields()
    {
        return [
            'blocked_phone_numbers.id',
            'blocked_phone_numbers.name',
            'blocked_phone_numbers.country_code',
            'blocked_phone_numbers.number',
            'blocked_phone_numbers.created_at',
            'blocked_phone_numbers.updated_at',
            'call_count'
        ];
    }

    static public function exports() : array
    {
        return [
            'id'                 => 'Id',
            'name'               => 'Name',
            'country_code'       => 'Country Code',
            'number'             => 'Number',
            'call_count'         => 'Calls',
            'created_at_local'   => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Blocked Numbers';
    }

    static public function exportQuery($user, array $input)
    {
        return BlockedPhoneNumber::select([
                                'blocked_phone_numbers.*',
                                DB::raw('(SELECT count(*) FROM blocked_calls WHERE blocked_calls.blocked_phone_number_id = blocked_phone_numbers.id) AS call_count'),
                                DB::raw("DATE_FORMAT(CONVERT_TZ(blocked_phone_numbers.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS created_at_local") 
                          ])
                          ->where('blocked_phone_numbers.account_id', $input['account_id']);
    }

    /**
     * Relationships
     * 
     */
    public function getCallCountAttribute()
    {
        return BlockedCall::where('blocked_phone_number_id', $this->id)->count();
    }

    /**
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-blocked-phone-number', [
            'company'            => $this->company_id,
            'blockedPhoneNumber' => $this->id
        ]);
    }

    /**
     * Get the kind
     * 
     */
    public function getKindAttribute()
    {
        return 'BlockedPhoneNumber';
    }

}
