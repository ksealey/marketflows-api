<?php

namespace Tests\Feature\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use \App\Models\Company\AudioClip;
use Storage;

class AudioClipTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing audio clips
     *
     * @group feature-audio-clips
     */
    public function testList()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id
        ]);

        $audioClip2 = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', route('list-audio-clips', [
            'company' => $this->company->id
        ]), [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message'         => 'success',
            'audio_clips'     => [
                ['id' => $audioClip->id],
                ['id' => $audioClip2->id]
            ],
            'result_count'    => 2,
            'limit'           => 25,
            'page'            => 1,
            'total_pages'     => 1
        ]);
    }

    /**
     * Test creating an audio clip
     * 
     * @group feature-audio-clips
     */
    public function testCreate()
    {
        $user = $this->createUser();

        Storage::fake();

        $audioClipFile = UploadedFile::fake()->create('audio.mpeg', 2048); 
        $name          = 'My new audio clip';

        $response = $this->json('POST', route('create-audio-clip', [
            'company' => $this->company->id
        ]), [
            'audio_clip' => $audioClipFile,
            'name'       => $name
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJson([
            'message' => 'created',
            'audio_clip' => [
                'name' => $name
            ]
        ]);
    }

    /**
     * Test reading an audio clip
     * 
     * @group feature-audio-clips
     */
    public function testRead()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', route('read-audio-clip', [
            'company' => $this->company->id,
            'audioClip' => $audioClip->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'success',
            'audio_clip' => [
                'id'   => $audioClip->id
            ]
        ]);
    }

    /**
     * Test updating an audio clip
     * 
     * @group feature-audio-clips
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        Storage::fake();

        $newAudioClipFile = UploadedFile::fake()->create('audio.mpeg', 2048); 
        $newName = 'Updated audio file';
        $response = $this->json('PUT', route('update-audio-clip', [
            'company' => $this->company->id,
            'audioClip'=> $audioClip->id
        ]), [
            'audio_clip' => $newAudioClipFile,
            'name'       => $newName
        ], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'updated',
            'audio_clip' => [
                'name' => $newName
            ]
        ]);

        $this->assertTrue(AudioClip::find($audioClip->id)->name == $newName);
    }

    /**
     * Test deleting an audio clip
     * 
     * @group feature-audio-clips
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        Storage::fake();

        $response = $this->json('DELETE', route('delete-audio-clip', [
            'company'   => $this->company->id,
            'audioClip' => $audioClip->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted'
        ]);

        Storage::assertMissing($audioClip->path);

        $this->assertTrue(AudioClip::find($audioClip->id) == null);
    }
}
