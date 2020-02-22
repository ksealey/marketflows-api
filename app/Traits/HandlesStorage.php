<?php
namespace App\Traits;

use App\Models\Company;

trait HandlesStorage
{
    static public function storagePath($accountId, $companyId, $path = '')
    {
        return 'accounts/' 
                . $accountId
                . '/companies/' 
                . $companyId
                . '/'
                . trim($path, '/');
    }
}