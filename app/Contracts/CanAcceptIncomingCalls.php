<?php
namespace App\Contracts;

use \App\Models\Company\PhoneNumberConfig; 

interface CanAcceptIncomingCalls
{
    /**
     * Return the id of the audio clip attached
     * 
     */
    public function audioClipId();


    /**
     * Determine if recording is enabled
     * 
     */
    public function recordingEnabled() : bool;

    /**
     * Determine the source of a call
     * 
     */
    public function source() : string;

    /**
     * Get the formatted number that a call should be forwarded to
     * 
     */
    public function forwardToPhoneNumber() : string;

    /**
     * Get configuration
     * 
     */
    public function getPhoneNumberConfig() : PhoneNumberConfig;
}