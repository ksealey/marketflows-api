<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Company;
use App\Events\CompanyJsPublishedEvent;
use App\Helpers\CDNHelper;
use Storage;

class BuildAndPublishCompanyJs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //  Build JS File
        $content = trim(strip_tags(view('js.main', ['company' => $this->company])->render()));
        
        //  Publish
        $publicPath = 'companies/' . $this->company->id . '/js';
        Storage::put('cdn/' . $publicPath . '/main.js', $content);

        //  Invalidate existing file
        CDNHelper::invalidate($publicPath . '/*');

        //var_dump($content); exit;

        //  Trigger event
        event(new CompanyJsPublishedEvent($this->company));
    }
}
