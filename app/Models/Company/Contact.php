<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Traits\PerformsExport;

class Contact extends Model
{
    use SoftDeletes, PerformsExport;
    
    protected $fillable = [
        'account_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alt_email',
        'alt_phone',
        'city',
        'state',
        'zip',
        'country',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public $appends = [
        'kind',
        'link'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_id'        => 'Company Id',
            'first_name'        => 'First Name',
            'last_name'         => 'Last Name',
            'phone'             => 'Phone',
            'email'             => 'Email',
            'alt_phone'         => 'Alt Phone',
            'alt_email'         => 'Alt Email',
            'city'              => 'City',
            'state'             => 'State',
            'zip'               => 'Zip',
            'country'           => 'Country',
            'created_at'        => 'Created',
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Contacts - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return Contact::where('contacts.company_id', $input['company_id']);
    }

    public function getKindAttribute()
    {
        return 'Contact';
    }

    public function getLinkAttribute()
    {
        return route('read-contact', [
            'company' => $this->company_id,
            'contact' => $this->id
        ]);
    }
}
