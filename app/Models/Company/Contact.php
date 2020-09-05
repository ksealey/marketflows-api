<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\Company\Call;

class Contact extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'uuid',
        'account_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'country_code',
        'phone',
        'city',
        'state',
        'zip',
        'country',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
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
            'country_code'      => 'Country Code',
            'phone'             => 'Phone',
            'email'             => 'Email',
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

    public function getActivityAttribute()
    {
        $calls = Call::where('contact_id', $this->id)
                     ->orderBy('created_at', 'ASC')
                     ->get();

        $activities = array_merge([
            [
                'kind'       => 'ActivityCreateContact',
                'created_at' => $this->created_at
            ],
        ], $calls->toArray());

        //
        //  Order by create date, asc
        //    
        usort($activities, function($a, $b){
            return $a['created_at'] <= $b['created_at'] ? -1 : 1;
        });

        return [
            'items'      => $activities,
            'call_count' => count($calls)
        ];
    }

    public function e164PhoneFormat()
    {
        return  ($this->country_code ? '+' . $this->country_code : '') 
                . $this->phone;
    }
}
