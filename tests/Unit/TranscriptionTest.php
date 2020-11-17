<?php

namespace Tests\Unit;

use \Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use \App\Services\TranscribeService;
use \App\Models\Company\Contact;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Jobs\TranscribeRecordingJob;
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

        $storagePath   = 'accounts/' . $company->account_id . '/companies/' . $company->id;
        $recordingPath = $storagePath . '/recordings/Call-' . $call->id . '.mp3';
        Storage::put($recordingPath, 'Foo Bar!!');

        $recording = factory(CallRecording::class)->create([
            'account_id'            => $call->account_id,
            'company_id'            => $call->company_id,
            'call_id'               => $call->id,
            'path'                  => $recordingPath,
            'transcription_path'    => null
        ]);

        $jobId = $recording->id . '-' . date('U');
        $url   = $this->faker()->url;
        $this->partialMock(TranscribeService::class, function($mock) use($recording, $jobId, $url){
            $mock->shouldReceive('startTranscription')
                 ->once()
                 ->andReturn($jobId);
                 
            $mock->shouldReceive('waitForUrl')
                 ->with($jobId)
                 ->once()
                 ->andReturn($url);

            $mock->shouldReceive('downloadFromUrl')
                 ->with($url)
                 ->once()
                 ->andReturn(json_decode(file_get_contents(__DIR__ . '/../data/transcription.json')));

            $mock->shouldReceive('deleteTranscription')
                 ->once()
                 ->with($jobId);
        });

        TranscribeRecordingJob::dispatch($company, $recording);
        
        Storage::assertExists($storagePath . '/transcriptions/Transcription-' . $recording->call_id . '.json');
        Storage::assertExists($storagePath . '/transcriptions/Transcription-' . $recording->call_id . '.txt');
    }
}
