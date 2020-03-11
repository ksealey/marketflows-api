<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BlockedPhoneNumber\BlockedCall;

class BlockedPhoneNumber extends Model
{
    use SoftDeletes;
    
    protected $table = 'blocked_phone_numbers';

    protected $hidden = [
        'deleted_at'
    ];

    protected $fillable = [
        'account_id',
        'company_id',
        'user_id',
        'name',
        'number', 
        'country_code',
        'batch_id' 
    ];

    protected $appends = [
        'link',
        'kind'
    ];

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
            'blockedPhoneNumberId' => $this->id
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
