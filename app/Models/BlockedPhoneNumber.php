<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockedPhoneNumber extends Model
{
    use SoftDeletes;
    
    protected $table = 'blocked_phone_numbers';

    protected $hidden = [
        'deleted_at',
    ];

    protected $fillable = [
        'account_id',
        'company_id',
        'user_id',
        'name',
        'number',  
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    /**
     * Relationships
     * 
     */
    public function calls()
    {
        return $this->hasMany('\App\Models\Company\PhoneNumber\Call');
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
