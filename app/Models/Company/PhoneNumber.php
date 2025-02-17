<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use \App\Models\Company\Call;
use \App\Models\Company\PhoneNumberConfig;
use \App\Contracts\Exportable;
use \App\Services\PhoneNumberService;
use Twilio\Rest\Client as TwilioClient;
use App\Traits\CanSwapNumbers;
use App;
use DB;
use Exception;
use Carbon\Carbon;

class PhoneNumber extends Model implements Exportable
{
    use SoftDeletes, CanSwapNumbers;

    const ERROR_CODE_INVALID     = 21421;
    const ERROR_CODE_UNAVAILABLE = 21422;

    const TYPE_LOCAL     = 'Local';
    const TYPE_TOLL_FREE = 'Toll-Free';

    const Online_SUB_CATEGORIES = [
        'Website',
        'Social Media',
        'Email'
    ];

    const Offline_SUB_CATEGORIES = [
        'TV',
        'Radio',
        'Newspaper',
        'Direct Mail',
        'Flyer',
        'Billboard',
        'Other'
    ];
    
    private $numberService;

    protected $fillable = [
        'uuid',
        'external_id',
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'phone_number_config_id',
        'keyword_tracking_pool_id',
        'category',
        'sub_category',
        'type',
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
        'is_paid',
        'is_organic',
        'is_direct',
        'is_search',
        'is_referral',
        'is_remarketing',
        
        'swap_rules',
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

    protected $dates = [
        'purchased_at',
        'disabled_at'
    ];
    
    protected $casts = [
        'voice' => 'boolean',
        'sms'   => 'boolean',
        'mms'   => 'boolean',
    ];

    static public function exports() : array
    {
        return [
            'id'                         => 'Id',
            'company_name'               => 'Company',
            'name'                       => 'Name',
            'country_code'               => 'Country Code',
            'number'                     => 'Number',
            'call_count'                 => 'Calls',
            'last_call_at_local'         => 'Last Call',
            'type'                       => 'Type',
            'category'                   => 'Category',
            'sub_category'               => 'Sub-Category',
            'source'                     => 'Source',
            'medium'                     => 'Medium',
            'campaign'                   => 'Campaign',
            'content'                    => 'Content',
            'status'                     => 'Status',
            'created_at_local'           => 'Purchase Date',
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
                                DB::raw("
                                    CASE WHEN phone_numbers.disabled_at IS NULL
                                        THEN 'Enabled'
                                    ELSE
                                        'Disabled'
                                    END AS status
                                "),
                                'companies.name AS company_name',
                                'phone_number_call_count.call_count',
                                DB::raw("DATE_FORMAT(CONVERT_TZ(phone_numbers.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS created_at_local"),
                                DB::raw("(SELECT MAX(calls.created_at) FROM calls WHERE phone_number_id = phone_numbers.id) AS last_call_at"),
                                DB::raw("DATE_FORMAT(CONVERT_TZ((SELECT MAX(calls.created_at) FROM calls WHERE phone_number_id = phone_numbers.id), 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS last_call_at_local")
                          ])
                          ->leftJoin('companies', function($join){
                            $join->on('phone_numbers.company_id', '=', 'companies.id');
                         })
                         ->leftJoin('phone_number_call_count', 'phone_number_call_count.phone_number_id', 'phone_numbers.id')
                          ->whereNull('phone_numbers.keyword_tracking_pool_id')
                          ->where('phone_numbers.company_id', $input['company_id']);
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

    public function keyword_tracking_pool()
    {
        return $this->belongsTo('\App\Models\Company\KeywordTrackingPool');
    }

    /**
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number', [
            'company'     => $this->company_id,
            'phoneNumber' => $this->id
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

    public function getCallCountAttribute()
    {
        return DB::table('phone_number_call_count')
                 ->select('phone_number_call_count.call_count')
                 ->from('phone_number_call_count')
                 ->where('phone_number_id', $this->id)
                 ->first()
                 ->call_count;
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
    
    public function getSwapRulesAttribute($rules)
    {
        return json_decode($rules);
    }

    /**
     * Determine the new date for this phone number
     * 
     */
    public function renewalDate()
    {
        $today         = today();
        $purchaseDate  = new Carbon($this->purchased_at);
        $renewDate     = new Carbon($today->format('Y-m-' . $purchaseDate->format('d')));
        if( $today->format('Y-m-d') >= $renewDate->format('Y-m-d') ) // If renew date has passed for month, move to next month
            $renewDate->addMonths('1');
            
        return $renewDate;
    }

    public function callsForPreviousDays(int $days)
    {
        $since = today()->subDays($days);
        $query = Call::where('phone_number_id', $this->id)
                        ->where('direction', 'Inbound')
                        ->where('created_at', '>=', $since);
                        
        return $query->count();
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
