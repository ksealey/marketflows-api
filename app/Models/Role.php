<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'policy'
    ];

    protected $hidden = [
        'account_id',
    ];

    /**
     * Create a role for a user that should only accedd reports.
     * This ships with any new user account
     * 
     */
    static public function createReportingRole($account)
    {
        return self::create([
            'account_id'    => $account->id,
            'name'          => 'Reporting User',
            'policy'        => json_encode([
                'policy' => [
                    [
                        'module'        => 'reports',
                        'permissions'   => '*'
                    ],
                ]
            ])
        ]);
    }
}
