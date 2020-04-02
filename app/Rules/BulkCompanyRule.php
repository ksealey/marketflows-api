<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use \App\Models\Company;
use \App\Models\UserCompany;


class BulkCompanyRule implements Rule
{
    protected $message;
    protected $user;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user  = $user;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $ids = json_decode($value);
        if( ! is_array($ids) ){
            $this->message = $attribute . ' must be provided as a valid json array.';
            return false;
        }

        if( count($ids) > 250 ){
            $this->message = 'A maximum of 250 ids are allowed.';
            return false;
        }

        foreach( $ids as $idx => $id ){
            if( ! is_numeric($id) ){
                $this->message = 'Invalid id at index ' . $idx . '.';
                return false;
            }
        }

        $companies = Company::withTrashed()
                            ->whereIn('id', $ids)
                            ->get();

        $userCompanyIds = array_column(
            UserCompany::withTrashed()
                        ->where('user_id', $this->user->id)
                        ->get()
                        ->toArray(), 
            'company_id'
        );

        $invalidIds = [];
        foreach($companies as $company){
            if( ! in_array($company->id, $userCompanyIds) ){
                $invalidIds[] = $company->id;
            }
        }

        if( count($invalidIds) ){
            $this->message = 'The following invalid ids were found ' . implode(',', $invalidIds);
            return false;
        }


        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
