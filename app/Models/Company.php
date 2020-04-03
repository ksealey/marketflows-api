<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies'; 

    protected $fillable = [
        'account_id',
        'user_id',
        'name',
        'industry',
        'country',
        'tts_voice',
        'tts_llanguage'
    ];

    protected $hidden = [
        'account_id',
        'user_id',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    public function getLinkAttribute()
    {
        return route('read-company', [
            'companyId' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Company';
    }

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }
}
