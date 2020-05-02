<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use \App\Models\Company\Call;
use \App\Models\Company\PhoneNumberPool;
use \App\Traits\CanSwapNumbers;
use \App\Models\Company\PhoneNumberConfig;
use \App\Contracts\Exportable;
use \App\Traits\PerformsExport;
use \App\Helpers\PhoneNumberManager;

use Twilio\Rest\Client as TwilioClient;
use App;
use DB;
use Exception;
use Carbon\Carbon;

class PhoneNumber extends Model implements Exportable
{
    use SoftDeletes, CanSwapNumbers, PerformsExport;

    const ERROR_CODE_INVALID     = 21421;
    const ERROR_CODE_UNAVAILABLE = 21422;

    const TYPE_LOCAL     = 'Local';
    const TYPE_TOLL_FREE = 'Toll-Free';
    
    private $numberManager;

    protected $fillable = [
        'uuid',
        'external_id',
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'phone_number_pool_id',
        'phone_number_config_id',
        'category',
        'sub_category',
        'type',
        'country',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms',
        'name',
        'source',
        'medium',
        'content',
        'campaign',
        'swap_rules',
        'assignments',
        'last_assigned_at',
        'purchased_at'
    ];

    protected $hidden = [
        'external_id',
        'deleted_at',
        'delete_by'
    ];

    protected $appends = [
        'link',
        'kind',
        'formatted_number'
    ];

    protected $casts = [
        'swap_rules' => 'array'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_id'        => 'Company Id',
            'category'          => 'Category',
            'sub_category'      => 'Sub-Category',
            'name'              => 'Name',
            'country_code'      => 'Country Code',
            'number'            => 'Number',
            'type'              => 'Type',
            'source'            => 'Source',
            'medium'            => 'Medium',
            'campaign'          => 'Campaign',
            'content'           => 'Content',
            'call_count'        => 'Calls',
            'last_call_at'      => 'Last Call Date',
            'created_at'        => 'Created',
            
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Numbers - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return PhoneNumber::select([
                                'phone_numbers.*',
                                DB::raw('(SELECT COUNT(*) FROM calls WHERE phone_number_id = phone_numbers.id) AS call_count'),
                                DB::raw('(SELECT MAX(calls.created_at) FROM calls WHERE phone_number_id = phone_numbers.id) AS last_call_at'),
                          ])
                          ->leftJoin('calls', 'calls.phone_number_id', 'phone_numbers.id')
                          ->whereNull('phone_numbers.phone_number_pool_id')
                          ->whereIn('phone_numbers.company_id', $input['company_id']);
    }

    /**
     * Relationships
     * 
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function phone_number_config()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumberConfig');
    }

    public function calls()
    {
        return $this->hasMany('\App\Models\Company\Call');
    }

    static private function client()
    {
        $config = config('services.twilio');

        return new TwilioClient($config['sid'], $config['token']);
    }

    static public function testClient()
    {
        $config = config('services.twilio');

        return new TwilioClient($config['test_sid'], $config['test_token']);
    }


    /**
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number', [
            'companyId'     => $this->company_id,
            'phoneNumberId' => $this->id
        ]);
    }

    /**
     * Get the kind
     * 
     */
    public function getKindAttribute()
    {
        return 'PhoneNumber';
    }

    /**
     * Get a formatted version of the number
     * 
     */
    public function getFormattedNumberAttribute()
    {
        return  '+' 
                . $this->country_code 
                . ' (' 
                . substr($this->number, 0, 3)
                . ') '
                . substr($this->number, 3, 3)
                . '-'
                . substr($this->number, 6, 4);
    }

    /**
     * List local phone numbers available for a given area code
     * 
     */
    
    
    /**
     * Release a phone number
     * 
     */

    public function bankOrRelease()
    {
        //  Move numbers to bank, release if needed
        $today            = today();
        $sevenDaysAgo     = today()->subDays(7);
        $fiveDaysFromNow  = today()->addDays(5);
        $callThreshold    = 21; // 3 calls per day for 7 days
 
        //  Determine renewal date
        $purchaseDate  = new Carbon($this->purchased_at);
        $renewDate     = new Carbon($today->format('Y-m-' . $purchaseDate->format('d')));
        if( $today->format('Y-m-d') >= $renewDate->format('Y-m-d') ) // If renew date has passed for month, move to next month
            $renewDate->addMonths('1');

        //  Determine call count for last 7 days
        $calls = Call::where('phone_number_id', $this->id)
                    ->where('created_at', '>=', $sevenDaysAgo)
                    ->get();
        
        $daysUntilRenew = $today->diffInDays($renewDate);
       
        if( $daysUntilRenew <= 5 ){
            return $this->release();
        }

        BankedPhoneNumber::create([
            'external_id'            => $this->external_id,
            'country'                => $this->country,
            'country_code'           => $this->country_code,
            'number'                 => $this->number,
            'voice'                  => $this->voice,
            'sms'                    => $this->sms,
            'mms'                    => $this->mms,
            'type'                   => $this->type,
            'calls'                  => count($calls),
            'purchased_at'           => $this->purchased_at,
            'release_by'             => $renewDate->subDays(2),
            'released_by_account_id' => $this->account_id,
            'status'                 => count($calls) > $callThreshold ? 'Banked' : 'Available',
        ]);
    }

    public function release()
    {
        return App::make(PhoneNumberManager::class)
                  ->release($this->external_id);
    }

    /**
     * Clean a phone number
     * 
     */
    static public function cleanPhone($phoneStr)
    {
        return preg_replace('/[^0-9]+/', '', $phoneStr);
    }

    /**
     * Pull phone number segment of a phone number
     * 
     */
    static public function number($phoneStr)
    {
        $phone = self::cleanPhone($phoneStr); 

        $neg = strlen($phone) >=  10 ? -10 : 0 - strlen($phone);

        return substr($phone, $neg);
    }

    /**
     * Pull country code segment of a phone number
     * 
     */
    static public function countryCode($phoneStr)
    {
        $fullPhone = self::cleanPhone($phoneStr);
        $phone     = self::number($phoneStr);

        $len = strlen($fullPhone) - strlen($phone);

        return substr($fullPhone, 0, $len);
    }

    /**
     * Get a formatted phone number
     * 
     */
    public function e164Format()
    {
        return  ($this->country_code ? '+' . $this->country_code : '') 
                . $this->number;
    }

    /**
     * Allow a number to be put be re-used
     * 
     */
    public function unassign()
    {
        $this->assigned_at = null;

        $this->save();

        return $this;
    }

    public function exposedData()
    {
        return [
            'uuid'          => $this->uuid,
            'country_code'  => $this->country_code,
            'number'        => $this->number
        ];
    }

    public function types()
    {
        return [
            self::TYPE_LOCAL,
            self::TYPE_TOLL_FREE
        ];
    }
}
