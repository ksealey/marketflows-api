<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Faker\Generator as Faker;
use App\Models\Account;
use App\Models\User;
use App\Helpers\TextToSpeech;
use App\Helpers\PhoneNumberManager;

class TextToSpeechTest extends TestCase
{
    use \Tests\CreatesAccount;
    
    /**
     * Test text to speech
     * 
     * @group tts
     */
    public function testTextToSpeech()
    {
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
            'text' => $text 
        ]);
        $response->assertStatus(200);
        $response->assertSee($data);
    }
}