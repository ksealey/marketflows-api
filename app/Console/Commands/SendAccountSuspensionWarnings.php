<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Jobs;
use \App\Models\Account;
use \App\Models\Billing;
use \App\Mail\SuspensionWarning3Days as SuspensionWarning3DaysMail;
use \App\Mail\SuspensionWarning7Days as SuspensionWarning7DaysMail;
use \App\Mail\SuspensionWarning9Days as SuspensionWarning9DaysMail;
use \App\Mail\SuspensionWarning10Days as SuspensionWarning10DaysMail;
use \App\Jobs\ReleaseAccountNumbersJob;
use DB;
use Mail;

class SendAccountSuspensionWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-account-suspension-warnings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send account suspension warnings';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //
        //  3 days past due
        //
        $accountIds = DB::table('billing')
                        ->select('account_id')
                        ->whereIn('id', function($query){
                            $query->select('billing_id')
                                  ->from('billing_statements')
                                  ->whereNull('paid_at')
                                  ->where('billing_period_ends_at', '<=', now()->subDays(3));
                        })
                        ->whereNull('next_suspension_warning_at')
                        ->where('suspension_warnings', 0)
                        ->groupBy('account_id')
                        ->get()
                        ->toArray();

        $accountIds = array_column($accountIds, 'account_id');

        Billing::whereIn('account_id', $accountIds)->update([
            'next_suspension_warning_at' => now()->addDays(4),
            'suspension_warnings'        => 1
        ]);
        
        $accountsPastDue = Account::whereIn('id', $accountIds)->get();
        foreach( $accountsPastDue as $account ){
            foreach($account->admin_users as $user){
                Mail::to($user->email)
                    ->queue(new SuspensionWarning3DaysMail($user, $account));
            } 
        }


        //
        //  7 days past due
        //
        $accountIds = DB::table('billing')
                        ->select('account_id')
                        ->whereIn('id', function($query){
                            $query->select('billing_id')
                                  ->from('billing_statements')
                                  ->whereNull('paid_at')
                                  ->where('billing_period_ends_at', '<=', now()->subDays(7));
                        })
                        ->whereNull('next_suspension_warning_at')
                        ->where('suspension_warnings', 1)
                        ->groupBy('account_id')
                        ->get()
                        ->toArray();

        $accountIds = array_column($accountIds, 'account_id');
        Billing::whereIn('account_id', $accountIds)->update([
            'next_suspension_warning_at' => now()->addDays(2),
            'suspension_warnings'        => 2
        ]);
        
        $accountsPastDue = Account::whereIn('id', $accountIds)->get();
        foreach( $accountsPastDue as $account ){
            //  Suspend account
            $account->suspended_at       = now();
            $account->suspension_message = 'Your account has been suspended for past due payments. Please pay all outstanding statements to reinstate your account.';
            $account->suspension_code    = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
            $account->save();

            foreach($account->admin_users as $user){
                //  Let the user know
                Mail::to($user->email)
                    ->queue(new SuspensionWarning7DaysMail($user, $account));
            } 
        }


        //
        //  9 days past due
        //
        $accountIds = DB::table('billing')
                        ->select('account_id')
                        ->whereIn('id', function($query){
                            $query->select('billing_id')
                                  ->from('billing_statements')
                                  ->whereNull('paid_at')
                                  ->where('billing_period_ends_at', '<=', now()->subDays(9));
                        })
                        ->whereNull('next_suspension_warning_at')
                        ->where('suspension_warnings', 2)
                        ->groupBy('account_id')
                        ->get()
                        ->toArray();

        $accountIds = array_column($accountIds, 'account_id');
        Billing::whereIn('account_id', $accountIds)->update([
            'next_suspension_warning_at' => now()->addDays(2),
            'suspension_warnings'        => 3
        ]);
        
        $accountsPastDue = Account::whereIn('id', $accountIds)->get();
        foreach( $accountsPastDue as $account ){
            foreach($account->admin_users as $user){
                //  Let the user know
                Mail::to($user->email)
                    ->queue(new SuspensionWarning9DaysMail($user, $account));
            } 
        }

        //
        //  10 days past due
        //
        $accountIds = DB::table('billing')
                        ->select('account_id')
                        ->whereIn('id', function($query){
                            $query->select('billing_id')
                                  ->from('billing_statements')
                                  ->whereNull('paid_at')
                                  ->where('billing_period_ends_at', '<=', now()->subDays(10));
                        })
                        ->whereNull('next_suspension_warning_at')
                        ->where('suspension_warnings', 3)
                        ->groupBy('account_id')
                        ->get()
                        ->toArray();

        $accountIds = array_column($accountIds, 'account_id');
        Billing::whereIn('account_id', $accountIds)->update([
            'next_suspension_warning_at' => now()->addDays(2),
            'suspension_warnings'        => 4
        ]);
        
        $accountsPastDue = Account::whereIn('id', $accountIds)->get();
        foreach( $accountsPastDue as $account ){
            //  Release phone numbers
            ReleaseAccountNumbersJob::dispatch($account);

            foreach($account->admin_users as $user){
                //  Let the user know
                Mail::to($user->email)
                    ->queue(new SuspensionWarning10DaysMail($user, $account));
            } 
        }

        return 0;
    }
}
