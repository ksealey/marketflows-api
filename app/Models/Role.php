<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Account;

class Role extends Model
{
    protected $fillable = [
        'account_id',
        'created_by',
        'name',
        'description',
        'admin_role',
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
            'name'          => 'Administrator',
            'description'   => 'System user without limitations. Only these users will get system alerts.',
            'admin_role'    => true,
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
            'description'   => 'Users that should only be able to view reporting. This would be an ideal role for clients of a marketing agency.',
            'admin_role'    => false,
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
