<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\User;
use App\Models\Account;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use App\Mail\BillingStatementAvailable as BillingStatementAvailableEmail;
use Exception;
use DB;
use Mail;

class CreateBillingStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $billing;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($billing)
    {
        $this->billing = $billing;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $billing = $this->billing;
        $account = $billing->account;

        $monthlyFee = $account->monthly_fee;
        $usage      = $account->currentUsage();
        $storage    = $account->currentStorage();
        $total      = $monthlyFee +
                      $usage['total']['cost'] + 
                      $storage['total']['cost'];

        DB::beginTransaction();

        try{
            //  Create statement
            $statement = BillingStatement::create([
                'account_id'                => $account->id,
                'billing_period_starts_at'  => $billing->period_starts_at,
                'billing_period_ends_at'    => $billing->period_ends_at,
                'paid_at'                   => $total ? null : now()
            ]);

            //  Create statement items
            $statementItems = [
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Monthly Service Fee',
                    'details'               => $account->pretty_account_type,
                    'total'                 => $monthlyFee
                ],
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Local Numbers',
                    'details'               => $usage['local']['numbers']['count'] . ' (Tier ' . Account::TIER_NUMBERS_LOCAL . ')',
                    'total'                 => $usage['local']['numbers']['cost'],
                ],
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Local Minutes',
                    'details'               => $usage['local']['minutes']['count'] . ' (Tier ' . Account::TIER_MINUTES_LOCAL . ')',
                    'total'                 => $usage['local']['minutes']['cost']
                ],
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Toll-Free Numbers',
                    'details'               => $usage['toll_free']['numbers']['count'] . ' (Tier ' . Account::TIER_NUMBERS_TOLL_FREE . ')',
                    'total'                 => $usage['toll_free']['numbers']['cost'],
                ],
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Toll-Free Minutes',
                    'details'               => $usage['toll_free']['minutes']['count'] . ' (Tier ' . Account::TIER_MINUTES_TOLL_FREE . ')',
                    'total'                 => $usage['toll_free']['minutes']['cost']
                ],      
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Storage - Call Recordings',
                    'details'               => number_format($storage['call_recordings']['size_gb'],2) . 'GB (Tier ' . Account::TIER_STORAGE. 'GB)',
                    'total'                 => $storage['call_recordings']['cost']
                ],
                [
                    'billing_statement_id'  => $statement->id,
                    'label'                 => 'Storage - Files',
                    'details'               => number_format($storage['files']['size_gb'],2) . 'GB (Tier ' . Account::TIER_STORAGE. 'GB)',
                    'total'                 => $storage['files']['cost']
                ]
            ];
            BillingStatementItem::insert($statementItems);

            //  Update billing period
            $billing->period_starts_at = (new Carbon($billing->period_ends_at))->addDays(1);
            $billing->period_ends_at   = (clone $billing->period_starts_at)->addMonths(1)->subDays(1);
            $billing->bill_at          = $statement->paid_at ? null : now(); // Do not set a bill date if there's no total
            $billing->save();
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        $users = $account->admin_users;

        foreach( $users as $user ){
            Mail::to($user->email)->send(new BillingStatementAvailableEmail($statement, $total));
        }
    }
}
