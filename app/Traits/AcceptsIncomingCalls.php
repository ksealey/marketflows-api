<?php
namespace App\Traits;

trait AcceptsIncomingCalls
{
    public function audioClipId()
    {
        $config = $this->getPhoneNumberConfig();

        return $config ? $config->audio_clip_id : null;
    }

    public function recordingEnabled() : bool
    {
        $config = $this->getPhoneNumberConfig();

        return $config ? $config->recordingEnabled() : false;
    }

    public function source() : string
    {
        $config = $this->getPhoneNumberConfig();        

        return $config ? $config->source() : '';
    }
    
    public function forwardToPhoneNumber() : string
    {
        $config = $this->getPhoneNumberConfig();
        if( ! $config )
            return null;
        
        return $config->forwardToPhoneNumber();
    }
}