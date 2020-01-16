<?php
namespace App\Helpers;
use DateInterval;

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

    static public function offsetTimeString(DateInterval $interval)
    {
        if( $years = $interval->y )
            return $years . ' year' . ( $years > 1 ? 's' : '') . ' ago';

        if( $months = $interval->m )
            return $months . ' month' . ( $months > 1 ? 's' : '') . ' ago';

        if( $days = $interval->d )
            return $days . ' day' . ( $days > 1 ? 's' : '') . ' ago';

        if( $hours = $interval->h )
            return $hours . ' hour' . ( $hours > 1 ? 's' : '') . ' ago';

        if( $minutes = $interval->i )
            return $minutes . ' minute' . ( $minutes > 1 ? 's' : '') . ' ago';

        if( $seconds = $interval->s )
            return 'A few seconds ago';

        return 'Just now';
    }
}