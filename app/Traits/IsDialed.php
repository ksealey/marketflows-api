<?php
namespace App\Traits;

trait IsDialed
{
    public function audioClipId()
    {
        return $this->audio_clip_id;
    }

    public function recordingEnabled() : bool
    {
        return $this->recording_enabled_at 
                ? true 
                : false;
    }

    public function phoneNumber() : string
    {
        return $this->joinPhone(
            $this->country_code, 
            $this->number
        );
    }

    public function forwardToPhoneNumber() : string
    {
        return $this->joinPhone(
            $this->forward_to_country_code, 
            $this->forward_to_number
        );
    }

    public function joinPhone($countryCode, $phoneNumber)
    {
        return  ($countryCode ? '+' . $countryCode : '') 
                . $phoneNumber;
    }
}