<?php
namespace App\Contracts;

interface CanBeDialed
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
     * Get the formatted number
     * 
     */
    public function phoneNumber() : string;

    /**
     * Get the formatted number that a call should be forwarded to
     * 
     */
    public function forwardToPhoneNumber() : string;
}