<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingStatement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'billing_id',
        'billing_period_starts_at',
        'billing_period_ends_at'
    ];

    public function items()
    {
        return $this->hasMany('App\Models\BillingStatementItem');
    }

    public function billing()
    {
        return $this->belongsTo('App\Models\Billing');
    }

    public function total()
    {
        $billing = $this->billing;

        $serviceQuantity        = $billing->quantity(Billing::ITEM_SERVICE);
        $serviceTotal           = $billing->total(Billing::ITEM_SERVICE, $serviceQuantity);

        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);

        return $serviceTotal + $localNumberTotal + $tollFreeNumberTotal + $localMinutesTotal + $tollFreeMinutesTotal + $transMinutesTotal + $storageTotal;
    }
}
