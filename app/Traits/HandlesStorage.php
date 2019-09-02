<?php
namespace App\Traits;

use App\Models\Company;

trait HandlesStorage
{
    static public function storagePath(Company $company, $path = '')
    {
        return 'accounts/' 
                . $company->account_id 
                . '/companies/' 
                . $company->id 
                . '/'
                . trim($path, '/');
    }
}