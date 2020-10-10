<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Company;
use App\Models\Company\ScheduledExport;
use App\Models\Company\Report;
use App\Models\Company\Webhook;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\KeywordTrackingPool;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Models\Company\AudioClip;
use App\Models\Company\PhoneNumber;
use App\Services\PhoneNumberService;
use App;
use Storage;

class DeleteCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $company;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, Company $company)
    {
        $this->user    = $user;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    { 
        $phoneService = App::make(PhoneNumberService::class);
        $company      = $this->company;
        $user         = $this->user;

        //  Remove scheduled exports
        ScheduledExport::where('company_id', $company->id)->delete();

        //  Remove reports
        Report::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Remove webhooks
        Webhook::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Remove contacts
        Contact::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);
       
        //  Remove phone number configs
        PhoneNumberConfig::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Keyword tracking pool
        KeywordTrackingPool::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Remove call recordings
        CallRecording::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Remove calls
        Call::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Audio clips
        AudioClip::where('company_id', $company->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //  Remove phone numbers
        PhoneNumber::where('company_id', $company->id)
                    ->get()
                    ->each(function($phoneNumber) use($user, $phoneService){
                        $phoneService->releaseNumber($phoneNumber);

                        $phoneNumber->deleted_at = now();
                        $phoneNumber->deleted_by = $user->id;
                        $phoneNumber->save();
                    });

        //  Wipe all files for company
        Storage::deleteDirectory('/accounts/' . $company->account_id . '/companies/' . $company->id);
    }
}
