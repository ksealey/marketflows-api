<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;

use App\Models\Company\AudioClip;
use Storage;

class PhoneNumberConfigTest extends TestCase
{
    use \Tests\CreatesAccount;
   
    /**
     * Test creatnig a phone number config
     * 
     */
    public function testCreatePhoneNumberConfig()
    {
          $configData = factory(PhoneNumberConfig::class)->make();
          
          $response = $this->json('POST', route('create-phone-number-config', [
                'name'                       => $configData->name,
                'forward_to_number'          => $configData->forward_to_number,
                'recording_enabled'          => 'bail|boolean',
                'caller_id'                  => 'bail|boolean',
                'whisper_message'            => 'bail|nullable|max:128',
                'greeting_message'           => 'bail|nullable|max:128',
                'greeting_audio_clip_id'     => ['bail', 'nullable', 'numeric', new AudioClipRule($company->id)],
                'keypress_enabled'           => 'bail|boolean',
                'keypress_audio_clip_id'     => ['bail', 'nullable',  'numeric', new AudioClipRule($company->id)],
                'keypress_message'           => 'bail|nullable|max:128',
          ]));
    }


}
