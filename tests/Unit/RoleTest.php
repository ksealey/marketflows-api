<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Role;
use \App\Models\Account;

class RoleTest extends TestCase
{
    /**
     * Test reporting role function
     *
     * @group roles
     */
    public function testCreateReportingRole()
    {
        $account = factory(Account::class)->create();

        $role    = Role::createReportingRole($account);

        $this->assertTrue($role != null);
        $this->assertTrue($role->locked == false);
    }
}
