<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use \App\Traits\AppliesConditions;
use \App\Traits\HandlesStorage;
use \App\Models\Alert;
use \App\Events\AlertEvent;
use Storage;
use DateTime;
use DateTimeZone;
use Cache;

class ExportResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AppliesConditions, HandlesStorage;

    protected $user;
    protected $input;
    protected $model;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model, $user, $input)
    {
        $this->model = $model;
        $this->user  = $user;
        $this->input = $input;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $model = $this->model;
        $user  = $this->user;
        $query = $model::exportQuery($this->user, $this->input);

        $startDate  = $this->input['start_date'] ?? null;
        $endDate    = $this->input['end_date'] ?? null;
        $conditions = $this->input['conditions'] ?? null;
        $rangeField = $this->input['range_field'];
        $orderBy    = $this->input['order_by'];
        $orderDir   = $this->input['order_dir'];

        if( $startDate || $endDate ){
            $userTZ = new DateTimeZone($user->timezone);
            $utcTZ  = new DateTimeZone('UTC');

            if( $startDate ){
                $startDate = new DateTime($startDate . ' 00:00:00', $userTZ);
                $startDate->setTimeZone($utcTZ);
                $query->where($rangeField, '>=', $startDate->format('Y-m-d H:i:s'));
            }

            if( $endDate  ){
                $endDate = new DateTime($endDate . ' 23:59:59', $userTZ);
                $endDate->setTimeZone($utcTZ);
                $query->where($rangeField, '<=', $endDate->format('Y-m-d H:i:s'));
            }
        }

        if( $conditions )
            $query = $this->applyConditions($query,  json_decode($conditions));

        $query->orderBy($orderBy, $orderDir);

        $tempFilePath = $model::onChunkedExport($query);
        $remotePath   = 'temp/exports/accounts/' . $this->user->account_id . '/' . str_random(64);
        $fileName     = $model::exportFileName() . '.xlsx';
        $expiresAt    = now()->addDays(1);
        
        //  Store remote file
        Storage::putFileAs($remotePath, new File($tempFilePath), $fileName, [
            'visibility'          => 'public', 
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0'
        ]);

        //  Remote Temporary file
        unlink($tempFilePath);

        //  Send Alert
        $alert = Alert::create([
            'user_id'       => $this->user->id,
            'type'          => Alert::TYPE_NOTIFICATION,
            'title'         => 'Your file export is ready',
            'message'       => 'Your file export is now available and will be accessible for 24 hours.',
            'url'           => trim(env('CDN_URL'), '/') . '/' . $remotePath . '/' . $fileName,
            'url_label'     => 'Download',
            'icon'          => 'file',
            'hidden_after'  =>  $expiresAt
        ]);

        event(new AlertEvent($this->user, $alert));
    }
}
