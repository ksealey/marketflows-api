<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\APICredential;
use App\Models\Account;
use App\Models\Company;
use App\Models\ApiCredntial;
use App\Models\PaymentMethod;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedCall;
use App\Models\Alert;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\SupportTicketAttachment;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use App\Jobs\DeleteCompanyJob;
use Storage;
use DB;

class DeleteAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, Account $account)
    {
        $this->user    = $user;
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $account = $this->account;
        $user    = $this->user;

        //  Remove api credentials
        APICredential::where('account_id', $account->id)
                     ->delete();

        //  Remove all users
        User::where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $user->id
            ]);

        //  Remove payment methods
        PaymentMethod::where('account_id', $account->id)->update([
            'deleted_at' => now(),
            'deleted_by' => $user->id
        ]);

        //  Remove blocked phone numbers
        BlockedPhoneNumber::where('account_id', $account->id)->update([
            'deleted_at' => now(),
            'deleted_by' => $user->id
        ]);

        //  Remove blocked calls
        BlockedCall::where('account_id', $account->id)->delete();

        //  Remove alerts
        Alert::where('account_id', $account->id)->delete();

        //  Remove support tickets
        SupportTicket::where('account_id', $account->id)->delete();

        //  Remove support ticket comments
        SupportTicketComment::where('account_id', $account->id)->delete();

        //  Remove support ticket attachments
        SupportTicketAttachment::where('account_id', $account->id)->delete();
        
        //  Remove companies and resources
        $companies = Company::where('account_id', $account->id)->get();
        foreach($companies as $company){
            $company->deleted_at = now();
            $company->deleted_by = $user->id;
            $company->save();

            DeleteCompanyJob::dispatch($user, $company, false);
        }

        $billing = $account->billing;

        //  Remove statements
        BillingStatement::where('billing_id', $billing->id);

        //  Remove statement items
        BillingStatementItem::whereIn('billing_statement_id',  function($query) use($billing){
            $query->select('id')
                  ->from('billing_statements')
                  ->where('billing_id', $billing->id);
        })
        ->delete();

        //  Remove billing
        $billing->delete();

        //  Remove all files
        Storage::deleteDirectory('/accounts/' . $account->id);
    }
}
