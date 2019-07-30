<?php
namespace App\Helpers;

class Formatter
{
    /**
     * Properly format a phone number
     * 
     * @param string $phone
     * 
     * @return string
     */
    static public function phone(string $phone)
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}