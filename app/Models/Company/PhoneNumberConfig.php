<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use DB;

class PhoneNumberConfig extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'company_id',
        
        'name',
        'forward_to_number',

        'greeting_enabled',
        'greeting_message_type',
        'greeting_message',
        'greeting_audio_clip_id',

        'keypress_enabled',
        'keypress_key',
        'keypress_timeout',
        'keypress_attempts',
        'keypress_directions_message',
        'keypress_error_message',
        'keypress_success_message',
        'keypress_failure_message',
        
        'whisper_enabled',
        'whisper_message',

        'keypress_conversion_enabled',
        'keypress_conversion_key_converted',
        'keypress_conversion_key_unconverted',
        'keypress_conversion_attempts',
        'keypress_conversion_timeout',
        'keypress_conversion_directions_message',
        'keypress_conversion_error_message',
        'keypress_conversion_success_message',
        'keypress_conversion_failure_message',

        'keypress_qualification_enabled',
        'keypress_qualification_key_qualified',
        'keypress_qualification_key_potential',
        'keypress_qualification_key_customer',
        'keypress_qualification_key_unqualified',
        'keypress_qualification_attempts',
        'keypress_qualification_timeout',
        'keypress_qualification_directions_message',
        'keypress_qualification_error_message',
        'keypress_qualification_success_message',
        'keypress_qualification_failure_message',

        'recording_enabled',

        'transcription_enabled',

        'created_by',
    ];

    protected $hidden = [
        'deleted_by',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $casts = [
        'recording_enabled' => 'boolean',
        'transcription_enabled' => 'boolean',
        'keypress_enabled' => 'boolean',
        'whisper_enabled' => 'boolean',
        'keypress_conversion_enabled' => 'boolean',
        'keypress_qualification_enabled'    => 'boolean'
    ];

    /**
     * Relationships
     * 
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    /**
     *   Attributes 
     *
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number-config', [
            'company'           => $this->company_id,
            'phoneNumberConfig' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'PhoneNumberConfig';
    }

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_name'       => 'Company',
            'name'              => 'Name',
            'forward_to_number' => 'Forwarding Number',
            'recording_enabled' => 'Recording Enabled',
            'transcription_enabled' => 'Transcription Enabled',
            'keypress_enabled'  => 'Keypress Enabled',
            'whisper_enabled'   => 'Whisper Enabled',
            'created_at_local'  => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Numbers Configurations - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return PhoneNumberConfig::select([
                                    'phone_number_configs.*',
                                    DB::raw("DATE_FORMAT(CONVERT_TZ(phone_number_configs.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS created_at_local")
                                ])
                                ->leftJoin('companies', 'companies.id', 'phone_number_configs.company_id')
                                ->where('company_id', $input['company_id']);
    }


    public function isInUse()
    {
        if( PhoneNumber::where('phone_number_config_id', $this->id)->count() )
            return true;

        return false;
    }

    public function forwardToPhoneNumber($country = 'US'){
        $number = trim($this->forward_to_number);
        
        if( strlen($number) > 10 ){
            $number = '+' . $number;
        }else{
            $countryCode = config('app.country_codes')[$country] ?? '1';
            $number      = '+' . $countryCode . $number;
        }
            
        return $number;
    }

    /**
     * Get a formatted version of a source
     * 
     */
    public function messageSource($source)
    {
        if( preg_match('/^http(s)?:\/\//i', $source) ){
            $source = parse_url($source, PHP_URL_HOST);
            if( $source ){
                $source = explode('.', $source);
                if( count($source) > 2 ){
                    $source = array_slice($source, -2, 2);
                }
                $source = implode('.', $source);
            }else{
                $source = 'Website Referral';
            }
        }
        return $source;
    }

    /**
     * Merge variables with with message field
     * 
     */
    public function message($field, $variables = [], $call = null)
    {
        //
        //  Inject global variables
        //
        $otherVariables = $this->variables();

        if( $call ){
            $otherVariables['source']   = $this->messageSource($call->source);
            $otherVariables['medium']   = $call->medium;
            $otherVariables['content']  = $call->content;
            $otherVariables['campaign'] = $call->campaign;
            $otherVariables['keyword']  = $call->keyword;
        }

        $variables = array_merge($otherVariables, $variables);

        //
        //  Turn keys into variable keys 
        //
        $vars = [];
        foreach( $variables as $key => $value ){
            $vars['${' . strtolower($key) . '}'] = $value;
        }

        return str_ireplace(
            array_keys($vars), 
            array_values($vars), 
            $this->$field
        );
    }

    public function variables($with = [])
    {
        $company = $this->company;

        return array_merge([
            'company_name'                          => $company->name,
            'forward_number'                        => $this->forward_to_number,
            'keypress_key'                          => $this->keypress_key,
            'keypress_attempts'                     => $this->keypress_attempts,
            'keypress_timeout'                      => $this->keypress_timeout,
            'keypress_conversion_key_converted'     => $this->keypress_conversion_key_converted,
            'keypress_conversion_key_unconverted'   => $this->keypress_conversion_key_unconverted,
            'keypress_conversion_attempts'          => $this->keypress_conversion_attempts,
            'keypress_conversion_timeout'           => $this->keypress_conversion_timeout,
            'keypress_qualification_key_qualified'  => $this->keypress_qualification_key_qualified,
            'keypress_qualification_key_potential'  => $this->keypress_qualification_key_potential,
            'keypress_qualification_key_customer'   => $this->keypress_qualification_key_customer,
            'keypress_qualification_key_unqualified'=> $this->keypress_qualification_key_unqualified,
            'keypress_qualification_attempts'       => $this->keypress_qualification_attempts,
            'keypress_qualification_timeout'        => $this->keypress_qualification_timeout
        ], $with);
    }
    
}
