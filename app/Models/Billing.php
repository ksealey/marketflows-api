<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use App\Models\BillingStatement;
use DB;
use \Carbon\Carbon;

class Billing extends Model
{
    use SoftDeletes;
    
    protected $table = 'billing';

    protected $fillable = [
        'account_id',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'suspension_warnings',
        'next_suspension_warning_at',
        'external_id'
    ];

    protected $hidden = [
        'suspension_warnings',
        'next_suspension_warning_at',
        'external_id',
        'deleted_at'
    ];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    const ITEM_SERVICE                  = 'Service';
    const ITEM_NUMBERS_LOCAL            = 'PhoneNumbers.Local';
    const ITEM_NUMBERS_TOLL_FREE        = 'PhoneNumbers.TollFree';
    const ITEM_MINUTES_LOCAL            = 'Minutes.Local';
    const ITEM_MINUTES_TOLL_FREE        = 'Minutes.TollFree';
    const ITEM_MINUTES_TRANSCRIPTION    = 'Minutes.Transcription';
    const ITEM_STORAGE_GB               = 'Storage.GB';

    const TIER_SERVICE                  = 0;
    const TIER_NUMBERS_LOCAL            = 10;
    const TIER_NUMBERS_TOLL_FREE        = 0;
    const TIER_MINUTES_LOCAL            = 500;
    const TIER_MINUTES_TOLL_FREE        = 0;
    const TIER_MINUTES_TRANSCRIPTION    = 0;
    const TIER_STORAGE_GB               = 1;

    const COST_SERVICE              = 39.99;
    const COST_NUMBERS_LOCAL        = 2.50;
    const COST_NUMBERS_TOLL_FREE    = 5.00;
    const COST_MINUTES_LOCAL        = 0.05;
    const COST_MINUTES_TOLL_FREE    = 0.07;
    const COST_MINUTES_TRANSCRIPTION= 0.05;
    const COST_STORAGE_GB           = 0.10;

    public function getPastDueAttribute()
    {
        $result = DB::table('billing_statement_items')
                          ->select(DB::raw('ROUND(SUM(total),2) AS past_due'))
                          ->whereIn('billing_statement_id', function($query){
                                $query->select('id')
                                      ->from('billing_statements')
                                      ->where('billing_id', $this->id)
                                      ->whereNull('paid_at')
                                      ->whereNull('deleted_at');
                          })
                          ->first();

        return floatval($result->past_due);
    }

    public function label($item)
    {
        switch($item)
        {
            case self::ITEM_SERVICE:
                return 'Monthly Service';

            case self::ITEM_NUMBERS_LOCAL:
                return 'Phone Number - Local';

            case self::ITEM_NUMBERS_TOLL_FREE:
                return 'Phone Number - Toll-Free';

            case self::ITEM_MINUTES_LOCAL:
                return 'Minutes - Local';

            case self::ITEM_MINUTES_TOLL_FREE:
                return 'Minutes - Toll-Free';

            case self::ITEM_MINUTES_TRANSCRIPTION:
                    return 'Minutes - Transcriptions';

            case self::ITEM_STORAGE_GB:
                return 'Storage';

            default:
                return 'Misc';
        }
    }

    public function quantity($item, $startDate, $endDate)
    {
        switch($item)
        {
            case self::ITEM_SERVICE:
                return 1;

            case self::ITEM_NUMBERS_LOCAL:
            case self::ITEM_NUMBERS_TOLL_FREE:
                //
                //  All numbers that are:
                //  Active
                //  Purchased in the billing period even if they are deleted
                //
                $query = DB::table('phone_numbers')
                            ->where('account_id', $this->account_id)
                            ->where('created_at', '<=', $endDate) // Created before the en
                            ->where(function($query) use($startDate, $endDate){
                                //  Active within timeframe
                                $query->whereNull('deleted_at')
                                      ->orWhereBetween('deleted_at', [$startDate, $endDate]);
                            });

                $query->where('type', $item == self::ITEM_NUMBERS_LOCAL ? 'Local' : 'Toll-Free');
                
                return $query->count();
            
            break;

            case self::ITEM_MINUTES_LOCAL:
            case self::ITEM_MINUTES_TOLL_FREE:
                $query = DB::table('calls')->select([
                            DB::raw('SUM(
                                CASE 
                                    WHEN duration <= 60
                                        THEN 1
                                    ELSE CEIL(duration / 60)
                                END
                            ) as total_minutes')
                        ])
                        ->where('account_id', $this->account_id)
                        ->where('created_at', '>=', $startDate)
                        ->where('created_at', '<=', $endDate);
             
                $query->where('type', $item == self::ITEM_MINUTES_LOCAL ? 'Local' : 'Toll-Free');

                return $query->first()->total_minutes ?: 0;
             
            case self::ITEM_MINUTES_TRANSCRIPTION:
                $query = DB::table('calls')->select([
                                DB::raw('SUM(
                                    CASE 
                                        WHEN call_recordings.duration <= 60
                                            THEN 1
                                        ELSE CEIL(call_recordings.duration / 60)
                                    END
                                ) as total_minutes')
                            ])
                            ->leftJoin('call_recordings', 'call_recordings.call_id', 'calls.id')
                            ->where('calls.account_id', $this->account_id)
                            ->where('calls.transcription_enabled', 1)
                            ->where('calls.recording_enabled', 1)
                            ->whereBetween('calls.created_at', [$startDate, $endDate]);

                return $query->first()->total_minutes ?: 0;

            case self::ITEM_STORAGE_GB:
                $query = DB::table('calls')
                            ->select([
                                DB::raw('SUM(call_recordings.file_size) as total_storage')
                            ])
                            ->join('call_recordings', function($join){
                                $join->on('call_recordings.call_id', '=', 'calls.id')
                                     ->whereNull('call_recordings.deleted_at');
                            })
                            ->whereNull('calls.deleted_at')
                            ->where('calls.account_id', $this->account_id)
                            ->where('calls.created_at', '<', $endDate);

                $sizeBytes = $query->first()->total_storage ?: 0;
                $sizeGB    = $sizeBytes ? round($sizeBytes / 1024 / 1024 / 1024, 2, PHP_ROUND_HALF_UP) : 0;

                return $sizeGB;

            default:
                return 0;
        }
    }

    public function price($item)
    {
        switch($item)
        {
            case self::ITEM_SERVICE:
                return self::COST_SERVICE;

            case self::ITEM_NUMBERS_LOCAL:
                return self::COST_NUMBERS_LOCAL;

            case self::ITEM_NUMBERS_TOLL_FREE:
                return self::COST_NUMBERS_TOLL_FREE;

            case self::ITEM_MINUTES_LOCAL:
                return self::COST_MINUTES_LOCAL;

            case self::ITEM_MINUTES_TOLL_FREE:
                return self::COST_MINUTES_TOLL_FREE;

            case self::ITEM_MINUTES_TRANSCRIPTION:
                return self::COST_MINUTES_TRANSCRIPTION;

            case self::ITEM_STORAGE_GB:
                return self::COST_STORAGE_GB;
            
            default:
                return 0;
        }
    }

    public function total($item, $quantity)
    {
        switch($item)
        {
            case self::ITEM_SERVICE:
                return $this->price($item);

            case self::ITEM_NUMBERS_LOCAL:
                if( ! $quantity || $quantity - self::TIER_NUMBERS_LOCAL <= 0 )
                    return 0;
                return round(($quantity - self::TIER_NUMBERS_LOCAL) * self::COST_NUMBERS_LOCAL, 2);

            case self::ITEM_NUMBERS_TOLL_FREE:
                if( ! $quantity || $quantity - self::TIER_NUMBERS_TOLL_FREE <= 0 )
                    return 0;
                return round(($quantity - self::TIER_NUMBERS_TOLL_FREE) * self::COST_NUMBERS_TOLL_FREE, 2);

            case self::ITEM_MINUTES_LOCAL:
                if( ! $quantity || $quantity - self::TIER_MINUTES_LOCAL <= 0 )
                    return 0;
                return round(($quantity - self::TIER_MINUTES_LOCAL) * self::COST_MINUTES_LOCAL, 2);

            case self::ITEM_MINUTES_TOLL_FREE:
                if( ! $quantity || $quantity - self::TIER_MINUTES_TOLL_FREE <= 0 )
                    return 0;
                return round(($quantity - self::TIER_MINUTES_TOLL_FREE) * self::COST_MINUTES_TOLL_FREE, 2);

            case self::ITEM_MINUTES_TRANSCRIPTION:
                if( ! $quantity || $quantity - self::TIER_MINUTES_TRANSCRIPTION <= 0 )
                    return 0;
                return round(($quantity - self::TIER_MINUTES_TRANSCRIPTION) * self::COST_MINUTES_TRANSCRIPTION, 2);

            case self::ITEM_STORAGE_GB:
                if( ! $quantity || $quantity - self::TIER_STORAGE_GB <= 0 )
                    return 0;
                return round(($quantity - self::TIER_STORAGE_GB) * self::COST_STORAGE_GB, 2);
            
            default:
                return 0;
        }
    }

    public function currentTotal()
    {
        return $this->current()['total'];
    }

    public function current()
    {
        $total   = 0;

        $billingPeriodStart     = new Carbon($this->billing_period_starts_at);
        $billingPeriodEnd       = new Carbon($this->billing_period_ends_at);
 
        $serviceQuantity        = $this->quantity(Billing::ITEM_SERVICE, $billingPeriodStart, $billingPeriodEnd);
        $servicePrice           = $this->price(Billing::ITEM_SERVICE);
        $serviceTotal           = $this->total(Billing::ITEM_SERVICE, $serviceQuantity);

        $localNumberQuantity    = $this->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localNumberPrice       = $this->price(Billing::ITEM_NUMBERS_LOCAL);
        $localNumberTotal       = $this->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);

        $tollFreeNumberQuantity = $this->quantity(Billing::ITEM_NUMBERS_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeNumberPrice    = $this->price(Billing::ITEM_NUMBERS_TOLL_FREE);
        $tollFreeNumberTotal    = $this->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);

        $localMinutesQuantity   = $this->quantity(Billing::ITEM_MINUTES_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localMinutesPrice      = $this->price(Billing::ITEM_MINUTES_LOCAL);
        $localMinutesTotal      = $this->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);

        $tollFreeMinutesQuantity= $this->quantity(Billing::ITEM_MINUTES_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeMinutesPrice   = $this->price(Billing::ITEM_MINUTES_TOLL_FREE);
        $tollFreeMinutesTotal   = $this->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);

        $transMinutesQuantity   = $this->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION, $billingPeriodStart, $billingPeriodEnd);
        $transMinutesPrice      = $this->price(Billing::ITEM_MINUTES_TRANSCRIPTION);
        $transMinutesTotal      = $this->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);

        $storageQuantity        = $this->quantity(Billing::ITEM_STORAGE_GB, $billingPeriodStart, $billingPeriodEnd);
        $storagePrice           = $this->price(Billing::ITEM_STORAGE_GB);
        $storageTotal           = $this->total(Billing::ITEM_STORAGE_GB, $storageQuantity);

        $total = (
            $serviceTotal + 
            $localNumberTotal + 
            $tollFreeNumberTotal + 
            $localMinutesTotal + 
            $tollFreeMinutesTotal + 
            $transMinutesTotal + 
            $storageTotal
        );

        $items = [
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_SERVICE),
                'quantity'             => $serviceQuantity,
                'price'                => $servicePrice,
                'price_formatted'      => number_format($servicePrice, 2),
                'total'                => $serviceTotal,
                'total_formatted'      => number_format($serviceTotal, 2),
                'free_tier'            => Billing::TIER_SERVICE
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_NUMBERS_LOCAL),
                'quantity'             => $localNumberQuantity,
                'price'                => $localNumberPrice,
                'price_formatted'      => number_format($localNumberPrice, 2),
                'total'                => $localNumberTotal,
                'total_formatted'      => number_format($localNumberTotal, 2),
                'free_tier'            => Billing::TIER_NUMBERS_LOCAL
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_NUMBERS_TOLL_FREE),
                'quantity'             => $tollFreeNumberQuantity,
                'price'                => $tollFreeNumberPrice,
                'price_formatted'      => number_format($tollFreeNumberPrice, 2),
                'total'                => $tollFreeNumberTotal,
                'total_formatted'      => number_format($tollFreeNumberTotal, 2),
                'free_tier'            => Billing::TIER_NUMBERS_TOLL_FREE
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_MINUTES_LOCAL),
                'quantity'             => $localMinutesQuantity,
                'price'                => $localMinutesPrice,
                'price_formatted'      => number_format($localMinutesPrice, 2),
                'total'                => $localMinutesTotal,
                'total_formatted'      => number_format($localMinutesTotal, 2),
                'free_tier'            => Billing::TIER_MINUTES_LOCAL
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_MINUTES_TOLL_FREE),
                'quantity'             => $tollFreeMinutesQuantity,
                'price'                => $tollFreeMinutesPrice,
                'price_formatted'      => number_format($tollFreeMinutesPrice, 2),
                'total'                => $tollFreeMinutesTotal,
                'total_formatted'      => number_format($tollFreeMinutesTotal, 2),
                'free_tier'            => Billing::TIER_MINUTES_TOLL_FREE
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'quantity'             => $transMinutesQuantity,
                'price'                => $transMinutesPrice,
                'price_formatted'      => number_format($transMinutesPrice, 2),
                'total'                => $transMinutesTotal,
                'total_formatted'      => number_format($transMinutesTotal, 2),
                'free_tier'            => Billing::TIER_MINUTES_TRANSCRIPTION
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $this->label(Billing::ITEM_STORAGE_GB),
                'quantity'             => $storageQuantity,
                'price'                => $storagePrice,
                'price_formatted'      => number_format($storagePrice, 2),
                'total'                => $storageTotal,
                'total_formatted'      => number_format($storageTotal, 2),
                'free_tier'            => Billing::TIER_STORAGE_GB
            ],
        ];
        
        foreach( $this->account->services as $service ){
            $_serviceTotal = $service->total();
            $_servicePrice = $service->price();
            $items[] = [
                'type'                 => 'SERVICE',
                'label'                => $service->label(),
                'quantity'             => $service->quantity(),
                'price'                => $_servicePrice,
                'price_formatted'      => number_format($_servicePrice, 2),
                'total'                => $_serviceTotal,
                'total_formatted'      => number_format($_serviceTotal, 2)
            ];

            $total += $_serviceTotal;
        }

        return [
            'items'           => $items,
            'total'           => $total,
            'total_formatted' => number_format($total, 2)
        ];
    }
}
