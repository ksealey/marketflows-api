<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedCall extends Model
{
    protected $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

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
