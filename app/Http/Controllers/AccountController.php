<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\Company;
use App\Models\Company\Call;
use App\Models\User;
use App\Rules\ParamNameRule;
use App\Jobs\DeleteAccountJob;
use DateTime;
use Validator;
use Exception;
use DB;
use \Carbon\Carbon;

class AccountController extends Controller
{
    public function read(Request $request)
    {
        return response($request->user()->account);
    }

    /**
     * Update account
     * 
     */
    public function update(Request $request)
    {
        $config    = config('services.twilio.languages');
        $languages = array_keys($config);
        $voiceKey  = $request->tts_language && in_array($request->tts_language, $languages) ? $request->tts_language : 'en-US';
        $voices    = array_keys($config[$voiceKey]['voices']); 

        $rules = [
            'name'                          => 'bail|min:1|max:64',
            'tts_language'                  => 'bail|in:' . implode(',', $languages),
            'tts_voice'                     => ['bail', 'required_with:tts_language', 'in:' . implode(',', $voices)],
            'source_param'                  => ['bail', new ParamNameRule()],
            'medium_param'                  => ['bail', new ParamNameRule()],
            'content_param'                 => ['bail', new ParamNameRule()],
            'campaign_param'                => ['bail', new ParamNameRule()],
            'keyword_param'                 => ['bail', new ParamNameRule()],
        ];

        $validator = validator($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;

        if( $request->filled('name') )
            $account->name = $request->name;

        if( $request->filled('tts_language') )
            $account->tts_language = $request->tts_language;

        if( $request->filled('tts_voice') )
            $account->tts_voice = $request->tts_voice; 

        if( $request->filled('source_param') )
            $account->source_param = $request->source_param;

        if( $request->filled('medium_param') )
            $account->medium_param = $request->medium_param;

        if( $request->filled('content_param') )
            $account->content_param = $request->content_param;

        if( $request->filled('campaign_param') )
            $account->campaign_param = $request->campaign_param;

        if( $request->filled('keyword_param') )
            $account->keyword_param = $request->keyword_param;

        $account->save();

        return response($account);
    }

    /**
     * Close Account
     * 
     */
    public function delete(Request $request)
    {
        if( ! $request->confirm_close ){
            return response([
                'error' => 'You must confirm that you would like to close the account. Do this by setting confirm_close to 1.'
            ], 400);
        }

        //
        //  Make sure there are no unpaid statements
        //
        $user       = $request->user();
        $account    = $user->account;
        $statements = BillingStatement::where('billing_id',  $account->billing->id)
                                        ->whereNull('paid_at')
                                        ->get();
        $accountAgeDays = $account->created_at->diff(now())->days;
        if( $accountAgeDays >= Billing::DAYS_FREE && count($statements) ){
            return response([
                'error' => 'You must first pay all unpaid statements to close your account'
            ], 400);
        }

        DeleteAccountJob::dispatch($user, $account);

        $account->deleted_at = now();
        $account->deleted_by = $user->id;
        $account->save();

        return response([
            'message' => 'Bye'
        ]);
    }

    public function summary(Request $request)
    {
        $user    = $request->user();
        $summary = DB::table('accounts')->select([
            DB::raw("(SELECT count(*) FROM companies WHERE account_id = '" . $user->account_id . "' AND deleted_at IS NULL) as company_count"),
            DB::raw("(SELECT count(*) FROM phone_numbers WHERE account_id = '" . $user->account_id . "' AND deleted_at IS NULL) as phone_number_count"),
            DB::raw("(SELECT count(*) FROM contacts WHERE account_id = '" . $user->account_id . "' AND deleted_at IS NULL) as contact_count"),
            DB::raw("(SELECT count(*) FROM calls WHERE account_id = '" . $user->account_id . "' AND deleted_at IS NULL) as call_count"),
        ])
        ->where('id', $user->account_id)
        ->first();

        $billing   = $user->account->billing;
        $storageGB = $billing->quantity(
            Billing::ITEM_STORAGE_GB, 
            new Carbon($billing->billing_period_starts_at),
            new Carbon($billing->billing_period_ends_at)
        );
        
        return response([
            'kind'  => 'Summary',
            'link'  => route('read-summary'), 
            'companies' => [
                'count' => $summary->company_count
            ],
            'phone_numbers' => [
                'count' => $summary->phone_number_count
            ],
            'contacts' => [
                'count' => $summary->contact_count
            ],
            'calls' => [
                'count' => $summary->call_count
            ],
            'storage' => [
                'count' => $storageGB
            ]
        ]);
    }
}
