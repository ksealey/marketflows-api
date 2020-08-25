<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use \App\Models\Payment;
use \App\Traits\PerformsExport;
use \Stripe\Stripe;
use \Stripe\Customer;
use \Stripe\Card;
use \Stripe\Charge as StripeCharge;
use Stripe\PaymentMethod as StripePaymentMethod;
use DB;
use Exception;
use DateTime;
use App;

class PaymentMethod extends Model
{
    use SoftDeletes, PerformsExport;

    protected $fillable = [
        'account_id',
        'external_id',
        'last_4',
        'expiration',
        'brand',
        'type',
        'primary_method',
        'last_used_at',
        'error',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = [
        'external_id',
        'deleted_by',
        'deleted_at',
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    protected $casts = [
        'primary_method' => 'boolean'
    ];

    static public function exports() : array
    {
        return [
            'id'               => 'Id',
            'last_4'           => 'Name',
            'brand'            => 'Brand',
            'created_at_local' => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Companies';
    }

    static public function exportQuery($user, array $input)
    {
        return PaymentMethod::select([
            'payment_methods.*',
            DB::raw("DATE_FORMAT(CONVERT_TZ(payment_methods.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local") 
        ])->where('account_id', $user->account_id);
    }

    /**
     * Relationships
     * 
     */
    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-payment-method', [
            'paymentMethod' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'PaymentMethod';
    }

    /**
     * Create a payment method using a token
     * 
     */
    static public function createFromToken(string $stripeToken, User $user, $primaryMethod = false)
    {
        //
        //  Create remote resources
        //
        $account = $user->account;
        $billing = $account->billing;

        Stripe::setApiKey(config('services.stripe.secret'));
        
        $card = Customer::createSource(
            $billing->external_id,
            ['source' => $stripeToken]
        );

        //  If this is the new primary method, unset existing
        if( $primaryMethod ){
            self::where('account_id', $user->account_id)
                ->update([ 'primary_method' => false ]);
        }

        $expiration = new DateTime($card->exp_year . '-' . $card->exp_month . '-01 00:00:00'); 
        $expiration->modify('last day of this month');
    
        return self::create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'external_id'    => $card->id,
            'last_4'         => $card->last4,
            'expiration'     => $expiration->format('Y-m-d'),
            'type'           => $card->funding,
            'brand'          => ucfirst($card->brand),
            'primary_method' => $primaryMethod
        ]);
    }

    /**
     * Check if payment method has a remote resource
     * 
     */
    public function hasRemoteResource()
    {
        return $this->getRemoteResource() ? true : false;
    }

    /**
     * Get payment's remote resource
     * 
     */
    public function getRemoteResource()
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if( ! $this->external_id )
            return null;

        try{
            return StripePaymentMethod::retrieve($this->external_id);
        }catch(Exception $e){
            return null;
        }
    }

    /**
     * Delete payment along wit it's remote resource
     * 
     */
    public function delete()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        
        //  Remove from remote resource
        if( $resource = $this->getRemoteResource() )
            $resource->detach();

        //  Now call the parent's delete method
        parent::delete();
    }

    /**
     * Determine if a payment method is valid
     * 
     */
    public function isValid()
    {
        return $this->error ? false : true;
    }
}
