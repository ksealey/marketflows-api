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

class AudioClipTest extends TestCase
{
    use \Tests\CreatesAccount;
    
    /**
     * Test creating an audio clip
     * 
     * @group audio-clips
     */
    public function testCreateAudioClip()
    {
        Storage::fake();

        $company = $this->createCompany();
        $audioClip = factory(AudioClip::class)->make();

        $response = $this->json('POST', route('create-audio-clip', [
            'company' => $company->id
        ]), [
            'name'       => $audioClip->name,
            'audio_clip' => UploadedFile::fake()->create('audio.mp3')
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'company_id' => $company->id,
            'name'       => $audioClip->name
        ]);

        $this->assertDatabaseHas('audio_clips', [
            'id' => $response['id']
        ]);

        $audioClip = AudioClip::find($response['id']);

        Storage::assertExists($audioClip->path);
    }

    /**
     * Test reading an audio clip
     * 
     * @group audio-clips
     */
    public function testReadAudioClip()
    {
        $company   = $this->createCompany();
        $audioClip = factory(AudioClip::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('read-audio-clip', [
            'company'   => $company->id,
            'audioClip' => $audioClip->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'company_id' => $company->id,
            'name'       => $audioClip->name
        ]);
    }

    /**
     * Test updating an audio clip
     * 
     * @group audio-clips
     */
    public function testUpdateAudioClip()
    {
        $company   = $this->createCompany();
        $audioClip = factory(AudioClip::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('PUT', route('update-audio-clip', [
            'company'   => $company->id,
            'audioClip' => $audioClip->id
        ]), [
            'name' => 'updated'       
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'company_id' => $company->id,
            'name'       => 'updated'
        ]);
    }

     /**
     * Test deleting an audio clip
     * 
     * @group audio-clips
     */
    public function testDeleteAudioClip()
    {
        Storage::fake();

        $company   = $this->createCompany();
        $audioClip = factory(AudioClip::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        Storage::put($audioClip->path, 'foobar');

        $response = $this->json('DELETE', route('delete-audio-clip', [
            'company'   => $company->id,
            'audioClip' => $audioClip->id
        ])); 
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertDatabaseMissing('audio_clips', [
            'id' => $audioClip->id,
            'deleted_at' => null
        ]);

            
        Storage::assertMissing($audioClip->path);
    }

    /**
     * Test deleting an audio clip attached to configuration fails
     * 
     * @group audio-clips
     */
    public function testDeleteAudioClipFailsWhenInUse()
    {
        Storage::fake();

        $company   = $this->createCompany();
        $audioClip = factory(AudioClip::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        Storage::put($audioClip->path, 'foobar');

        $config    = factory(PhoneNumberConfig::class)->create([
            //'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'greeting_audio_clip_id' => $audioClip->id
        ]);

        $response = $this->json('DELETE', route('delete-audio-clip', [
            'account_id' => $this->account->id,
            'company'   => $company->id,
            'audioClip' => $audioClip->id
        ])); 
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'This audio clip is in use - please remove from all number configurations and try again.'
        ]);

        $this->assertDatabaseHas('audio_clips', [
            'id'         => $audioClip->id,
            'deleted_at' => null
        ]);

        Storage::assertExists($audioClip->path);
    }

}
