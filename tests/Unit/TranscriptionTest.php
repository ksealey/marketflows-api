<?php

namespace Tests\Unit;

use \Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use \App\Services\TranscribeService;
use \App\Models\Company\Contact;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App;
use \Storage;

class TranscriptionTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test transcribing a recording
     * 
     * @group transcriptions
     */
    public function testTranscribeRecording()
    {
        Storage::fake();

        $company        = $this->createCompany();
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);
        
        $call = factory(Call::class)->create([
            'account_id'        => $contact->account_id,
            'company_id'        => $contact->company_id,
            'contact_id'        => $contact->id,
            'phone_number_id'   => $phoneNumber->id,
            'phone_number_name' => $phoneNumber->name,
            'created_at'        => now()->subDays(2)
        ]);

        $path = '/accounts/' . $this->account->id . '/companies/' . $company->id . '/Call-' . $call->id . '.mp3';
        Storage::put($path, 'blah blah!');

        $recording = factory(CallRecording::class)->create([
            'call_id' => $call->id,
            'path' => $path,
            'transcription_path' => ''
        ]);

        $jobId = $recording->id . '-' . date('U');
        $url   = $this->faker()->url;
        $mock  = $this->partialMock(TranscribeService::class, function($mock) use($recording, $jobId, $url){
            $mock->shouldReceive('startTranscription')
                 ->with($recording, 'en-US')
                 ->andReturn($jobId);
                 
            $mock->shouldReceive('waitForUrl')
                 ->with($jobId)
                 ->andReturn($url);

            $mock->shouldReceive('downloadFromUrl')
                 ->with($url)
                 ->andReturn(json_decode(file_get_contents(__DIR__ . '/../data/transcription.json')));

            $mock->shouldReceive('deleteTranscription')
                 ->with($jobId);
        });

        $mock->transcribe($recording);

        Storage::assertExists(str_replace('recordings/Call-' . $recording->call_id . '.mp3', 'transcriptions/Call-' . $recording->call_id . '.json', $path));
    }
}
