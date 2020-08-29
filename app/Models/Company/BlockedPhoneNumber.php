<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\BlockedPhoneNumber\BlockedCall;
use DB;

class BlockedPhoneNumber extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'company_id',
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
            'company_id' => 'Company Id',
            'name'       => 'Name',
            'number'     => 'Number',
            'call_count' => 'Calls',
            'created_at' => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Blocked Numbers - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return BlockedPhoneNumber::select([
                                'blocked_phone_numbers.*',
                                DB::raw('(SELECT count(*) FROM blocked_calls WHERE blocked_calls.blocked_phone_number_id = blocked_phone_numbers.id) AS call_count')
                          ])
                          ->where('blocked_phone_numbers.company_id', $input['company_id']);
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
        return route('read-company-blocked-phone-number', [
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
