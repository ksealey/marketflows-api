<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use DB;

class Billing extends Model
{
    use SoftDeletes;
    
    protected $table = 'billing';

    protected $fillable = [
        'account_id',
        'billing_period_starts_at',
        'billing_period_ends_at'
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

    const TIER_NUMBERS_LOCAL            = 10;
    const TIER_NUMBERS_TOLL_FREE        = 0;
    const TIER_MINUTES_LOCAL            = 500;
    const TIER_MINUTES_TOLL_FREE        = 0;
    const TIER_MINUTES_TRANSCRIPTION    = 0;
    const TIER_STORAGE_GB               = 10;

    const COST_SERVICE              = 39.99;
    const COST_NUMBERS_LOCAL        = 2.50;
    const COST_NUMBERS_TOLL_FREE    = 4.00;
    const COST_MINUTES_LOCAL        = 0.04;
    const COST_MINUTES_TOLL_FREE    = 0.07;
    const COST_MINUTES_TRANSCRIPTION= 0.03;
    const COST_STORAGE_GB           = 0.10;

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

    public function quantity($item)
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
                            ->where(function($query){
                                $query->where(function($q){
                                            $q->where('created_at', '>=', $this->billing_period_starts_at)
                                              ->where('created_at', '<=', $this->billing_period_ends_at);
                                        })
                                        ->orWhere(function($q){
                                            $q->whereNull('deleted_at');
                                        });
                            });

                $query->where('type', $item == self::ITEM_NUMBERS_LOCAL ? 'Local' : 'Toll-Free');
                
                return $query->count();
            
            break;

            case self::ITEM_MINUTES_LOCAL:
            case self::ITEM_MINUTES_TOLL_FREE:
                $query = DB::table('calls')->select([
                            DB::raw('SUM(
                                CASE 
                                    WHEN duration < 60
                                        THEN 1
                                    ELSE CEIL(duration / 60)
                                END
                            ) as total_minutes')
                        ])
                        ->where('account_id', $this->account_id)
                        ->where('created_at', '>=', $this->billing_period_starts_at)
                        ->where('created_at', '<=', $this->billing_period_ends_at);
             
                $query->where('type', $item == self::ITEM_MINUTES_LOCAL ? 'Local' : 'Toll-Free');

                return $query->first()->total_minutes ?: 0;
             
            case self::ITEM_MINUTES_TRANSCRIPTION:
                $query = DB::table('calls')->select([
                                DB::raw('SUM(
                                    CASE 
                                        WHEN duration < 60
                                            THEN 1
                                        ELSE CEIL(duration / 60)
                                    END
                                ) as total_minutes')
                            ])
                            ->join('transcriptions', 'transcriptions.call_id', '=', 'calls.id')
                            ->where('account_id', $this->account_id)
                            ->where('created_at', '>=', $this->billing_period_starts_at)
                            ->where('created_at', '<=', $this->billing_period_ends_at);

                return $query->first()->total_minutes ?: 0;

            case self::ITEM_STORAGE_GB:
                $query = DB::table('calls')
                            ->select([
                                DB::raw(
                                    'SUM(call_recordings.file_size) as total_storage'
                                )
                            ])
                            ->join('call_recordings', 'call_recordings.call_id', '=', 'calls.id')
                            ->where('calls.account_id', $this->account_id)
                            ->where('calls.created_at', '<', $this->billing_period_ends_at);

                $sizeBytes = $query->first()->total_storage ?: 0;
                $sizeGB    = $sizeBytes ? ceil($sizeBytes / 1024 / 1024 / 1024) : 0;

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

    public function total($item, $quantity = null)
    {
        if( $quantity === null )
            $quantity = $this->quantity($item);
        
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
}
