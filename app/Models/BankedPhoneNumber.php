<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankedPhoneNumber extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'external_id',
        'country',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms', 
        'type',
        'calls',
        'purchased_at',
        'release_by',
        'released_by_account_id',
        'status'
    ];

    /**
     * Search for available numbers
     *
     */
    static public function availableNumbers($accountId, string $country, string $type, $startsWith = '', $count = 1)
    {
        $bankedQuery = BankedPhoneNumber::where('status', 'Available')
                                        ->where('released_by_account_id', '!=', $accountId)
                                        ->where('country', $country)
                                        ->where('type', $type);
        if( $startsWith )
            $bankedQuery->where('number', 'like', $startsWith . '%');

        return $bankedQuery->orderBy('release_by', 'ASC')
                           ->limit($count)
                           ->get();
    }
}
