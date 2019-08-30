<?php

namespace Tests\Feature;

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
     * @group audio-clips
     */
    public function testList()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $audioClip2 = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $user->company_id . '/audio-clips', [], $this->authHeaders());
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
     * @group audio-clips
     */
    public function testCreate()
    {
        $user = $this->createUser();

        Storage::fake();

        $audioClipFile = UploadedFile::fake()->create('audio.mpeg', 2048); 
        $name = 'My new audio clip';

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $user->company_id . '/audio-clips', [
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
     * @group audio-clips
     */
    public function testRead()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $user->company_id . '/audio-clips/' . $audioClip->id, [], $this->authHeaders());

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
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $user->company_id . '/audio-clips/' . $audioClip->id, [
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

        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $user->company_id . '/audio-clips/' . $audioClip->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted'
        ]);

        Storage::assertMissing($audioClip->path);

        $this->assertTrue(AudioClip::find($audioClip->id) == null);
    }
}
