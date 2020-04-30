<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Faker\Generator as Faker;
use App\Models\Account;
use App\Models\User;
use App\Helpers\TextToSpeech;

class TextToSpeechTest extends TestCase
{
    /**
     * Test text to speech
     * 
     * @group tts
     */
    public function testTextToSpeech()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id' => $account->id,
        ]);

        $text  = 'Hello World';
        $data  = 'foobar';
        $this->mock(TextToSpeech::class, function ($mock) use($text, $data){
            $mock->shouldReceive('say')
                 ->once()
                 ->with('en-US', 'Joanna', $text)
                 ->andReturn([
                     'AudioStream' => $data
                ]);
        });

        $response = $this->json('POST', route('text-to-speech-say'), [
            'text'       => $text 
        ], [
            'Authorization' => 'Bearer ' . $user->auth_token
        ]);
        $response->assertStatus(200);
        $response->assertSee($data);
    }
}