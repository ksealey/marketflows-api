<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Account;

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
     * Create a role for a user that can make any action.
     * 
     * @param Account $account      The associated account
     */
    static public function createAdminRole(Account $account)
    {
        return self::create([
            'account_id'    => $account->id,
            'name'          => 'Admin User',
            'policy'        => json_encode([
                'policy' => [
                    [
                        'module'        => '*',
                        'permissions'   => '*'
                    ],
                ]
            ])
        ]);
    }

    /**
     * Create a role for a user that should only accedd reports.
     * 
     * @param Account $account      The associated account
     * 
     * @return Role
     */
    static public function createReportingRole(Account $account)
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
