<?php

namespace App\Mail\Development;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Development\SuggestedFeature as SuggestedFeatureModel;

class SuggestedFeature extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $suggestedFeature;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, SuggestedFeatureModel $suggestedFeature)
    {
        $this->user = $user;
        $this->suggestedFeature = $suggestedFeature;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.development.suggested-feature')->subject('New Suggested Feature');
    }
}
