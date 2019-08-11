<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use \App\Models\AudioClip;
use Storage;

class AudioClipTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing audio clips
     *
     * @group audio-clips
     */
    public function testList()
    {
        $user = $this->createUser();

        $audioClip = factory(\App\Models\AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $audioClip2 = factory(\App\Models\AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/audio-clips', [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'audio_clips' => [
                [
                    'id'
                ],
                [
                    'id'
                ],
            ]
        ]);

        $response->assertJson([
            'message'      => 'success',
            'ok'           => true,
            'result_count' => 2,
            'total_count'  => 2
        ]);
    }

    /**
     * Test creating an audio clip
     * 
     * @group audio-clips
     */
    public function testCreate()
    {
        $user = $this->createUser();

        Storage::fake();

        $audioClipFile = UploadedFile::fake()->create('audio.mpeg', 2048); 

        $response = $this->json('POST', 'http://localhost/v1/audio-clips', [
            'audio_clip' => $audioClipFile,
            'name'       => 'My new audio clip'
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJsonStructure([
            'message',
            'ok',
            'audio_clip' => [
                'id'
            ]
        ]);
    }

    /**
     * Test reading an audio clip
     * 
     * @group audio-clips
     */
    public function testRead()
    {
        $user = $this->createUser();

        $audioClip = factory(\App\Models\AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/audio-clips/' . $audioClip->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'ok',
            'audio_clip' => [
                'id'
            ]
        ]);
    }

    /**
     * Test updating an audio clip
     * 
     * @group audio-clips
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        Storage::fake();

        $newAudioClipFile = UploadedFile::fake()->create('audio.mpeg', 2048); 
        $newName = 'Updated audio file';
        $response = $this->json('PUT', 'http://localhost/v1/audio-clips/' . $audioClip->id, [
            'audio_clip' => $newAudioClipFile,
            'name'       => $newName
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'ok',
            'audio_clip' => [
                'id'
            ]
        ]);

        $this->assertTrue(AudioClip::find($audioClip->id)->name == $newName);
    }

    /**
     * Test deleting an audio clip
     * 
     * @group audio-clips
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        Storage::fake();

        $response = $this->json('DELETE', 'http://localhost/v1/audio-clips/' . $audioClip->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'ok',
        ]);

        Storage::assertMissing($audioClip->path);

        $this->assertTrue(AudioClip::find($audioClip->id) == null);
    }
}
