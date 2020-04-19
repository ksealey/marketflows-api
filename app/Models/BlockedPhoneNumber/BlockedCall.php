<?php

namespace App\Models\BlockedPhoneNumber;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\PerformsExport;


class BlockedCall extends Model
{
    use SoftDeletes, PerformsExport;
    
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';


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
        return 'Blocked Calls - ' . $input['blocked_phone_number_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return BlockedCall::select([
                                'blocked_calls.*', 
                                'phone_numbers.number AS phone_number', 
                                'phone_numbers.country_code AS phone_number_country_code', 
                                'phone_numbers.name AS phone_number_name'
                            ])
                            ->where('blocked_calls.blocked_phone_number_id', $input['blocked_phone_number_id'])
                            ->leftJoin('phone_numbers', 'phone_numbers.id', 'blocked_calls.phone_number_id');
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
