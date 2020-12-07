<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Jobs;
use \App\Models\Alert;
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
        // Send warnings for accounts that are 3 days past due
        //
        $accounts = Account::where('suspension_warnings', 0)
                           ->whereIn('id', function($query){
                                $query->select('account_id')
                                    ->from('billing')
                                    ->whereIn('billing.id', function($query){
                                        $query->select('billing_id')
                                                ->from('billing_statements')
                                                ->whereNull('paid_at')
                                                ->where('billing_period_ends_at', '<=', now()->subDays(3));
                                    });
                            })
                            ->get();

        foreach( $accounts as $account ){
            $account->next_suspension_warning_at = now()->addDays(4);
            $account->suspension_warnings        = 1;
            $account->suspension_code            = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
            $account->save();

            foreach($account->admin_users as $user){
                Alert::create([
                    'account_id'    => $user->account_id,
                    'user_id'       => $user->id,  
                    'category'      => Alert::CATEGORY_PAYMENT,
                    'type'          => Alert::TYPE_DANGER,
                    'title'         => 'Pending account suspension',
                    'message'       => 'Your account is pending suspension for unpaid statements',
                    'hidden_after'  => now()->addDays(4)
                ]);

                Mail::to($user->email)
                    ->queue(new SuspensionWarning3DaysMail($user, $account));
            }
        }


        //
        //  7 days past due
        //
        $accounts = Account::where('suspension_warnings', 1) // We only need to check for warnings since it will be reset when payment is made
                            ->where('next_suspension_warning_at', '<', now())
                            ->get();
        
        foreach( $accounts as $account ){
            //  Suspend account
            $account->next_suspension_warning_at = now()->addDays(2);
            $account->suspension_warnings        = 2;
            $account->suspended_at               = now();
            $account->suspension_code            = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
            $account->suspension_message         = 'Your account has been suspended for past due payments. Please pay all outstanding statements to reinstate your account.';
            $account->save();

            foreach($account->admin_users as $user){
                Alert::create([
                    'account_id'    => $user->account_id,
                    'user_id'       => $user->id,  
                    'type'          => Alert::TYPE_DANGER,
                    'category'      => Alert::CATEGORY_PAYMENT,
                    'title'         => 'Account suspended - numbers pending release',
                    'message'       => 'Your account has been suspended for past due statements. You are no longer able to receive calls and your numbers are pending release in 3 days. To re-enable your account please pay all past due statements.',
                    'hidden_after'  => now()->addDays(2)
                ]);

                //  Let the user know
                Mail::to($user->email)
                    ->queue(new SuspensionWarning7DaysMail($user, $account));
            } 
        }

        //
        //  9 days past due
        //
        $accounts = Account::where('suspension_warnings', 2) // We only need to check for warnings since it will be reset when payment is made
                            ->where('next_suspension_warning_at', '<', now())
                            ->get();

        foreach( $accounts as $account ){
            $account->next_suspension_warning_at = now()->addDays(2);
            $account->suspension_warnings        = 3;
            $account->suspension_code            = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
            $account->suspension_message         = 'Your account has been suspended for past due payments. Your numbers are pending release tomorrow. Please pay all outstanding statements to reinstate your account.';
            $account->save();

            foreach( $account->admin_users as $user ){
                Alert::create([
                    'account_id'    => $user->account_id,
                    'user_id'       => $user->id,  
                    'category'      => Alert::CATEGORY_PAYMENT,
                    'type'          => Alert::TYPE_DANGER,
                    'title'         => 'Account suspended - numbers pending release tomorrow',
                    'message'       => 'Your account has been suspended for past due statements. You are no longer able to receive calls and your numbers are pending release in 24 hours. To re-enable your account please pay all past due statements.',
                    'hidden_after'  => now()->addDays(2)
                ]);

                //  Let the user know
                Mail::to($user->email)
                    ->queue(new SuspensionWarning9DaysMail($user, $account));
            } 
        }

        
        //
        //  10 days past due
        //
        $accounts = Account::where('suspension_warnings', 3) // We only need to check for warnings since it will be reset when payment is made
                            ->where('next_suspension_warning_at', '<', now())
                            ->get();
        
        foreach( $accounts as $account ){
            $account->next_suspension_warning_at = null;
            $account->suspension_warnings        = 4;
            $account->suspension_code            = Account::SUSPENSION_CODE_OUSTANDING_BALANCE;
            $account->save();

            //  Release phone numbers
            ReleaseAccountNumbersJob::dispatch($account);

            foreach($account->admin_users as $user){
                //  Let the user know
                Alert::create([
                    'account_id'    => $user->account_id,
                    'user_id'       => $user->id,  
                    'category'      => Alert::CATEGORY_PAYMENT,
                    'type'          => Alert::TYPE_DANGER,
                    'title'         => 'Account suspended - phone numbers released',
                    'message'       => 'Your account has been suspended for past due statements. All phone numbers associated with your account has been released. To re-enable your account please pay all past due statements.'
                ]);

                Mail::to($user->email)
                    ->queue(new SuspensionWarning10DaysMail($user, $account));
            } 
        }
        
        return 0;
    }
}
