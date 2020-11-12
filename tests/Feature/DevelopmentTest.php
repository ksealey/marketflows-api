<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Mail\Development\SuggestedFeature as SuggestedFeatureMail;
use App\Mail\Development\BugReport as BugReportMail;
use Mail;

class DevelopmentTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test suggesting a feature
     * 
     * @group development
     */
    public function testSuggestFeature()
    {
        Mail::fake();

        $faker   = $this->faker();
        $url     = $faker->url;
        $details = $faker->realText(500);
        $response = $this->json('POST', route('suggest-feature'), [
            'url'     => $url,
            'details' => $details
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'url' => $url,
            'details' => $details,
            'created_by' => $this->user->id
        ]);

        $this->assertDatabaseHas('suggested_features', [
            'id'      => $response['id'],
            'url'     => $url,
            'details' => $details
        ]);

        Mail::assertQueued(SuggestedFeatureMail::class, function($mail){
            return $mail->bcc[0]['address'] === config('mail.to.development.address') &&
                   $mail->to[0]['address']  === $this->user->email;
        });
    }

    /**
     * Test reporting a big
     * 
     * @group development
     */
    public function testReportBug()
    {
        Mail::fake();

        $faker   = $this->faker();
        $url     = $faker->url;
        $details = $faker->realText(500);
        $response = $this->json('POST', route('report-bug'), [
            'url'     => $url,
            'details' => $details
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'url' => $url,
            'details' => $details,
            'created_by' => $this->user->id
        ]);

        $this->assertDatabaseHas('bug_reports', [
            'id'      => $response['id'],
            'url'     => $url,
            'details' => $details
        ]);

        Mail::assertQueued(BugReportMail::class, function($mail){
            return $mail->bcc[0]['address'] === config('mail.to.development.address') &&
                   $mail->to[0]['address']  === $this->user->email;
        });
    }
}
