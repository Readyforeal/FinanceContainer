<?php
namespace App\Jobs;

use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Services\PlaidService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PlaidSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public PlaidConnection $plaidConnection;

    public function __construct(PlaidConnection $connection)
    {
        $this->plaidConnection = $connection;
        $this->onQueue('default');
    }

    public function handle(PlaidService $plaid): void
    {
        $cursor = $this->plaidConnection->cursor;

        do {
            $result = $plaid->syncTransactions($this->plaidConnection->access_token, $cursor);
            $this->processAdded($result['added']);
            $this->processModified($result['modified']);
            $this->processRemoved($result['removed']);
            $cursor = $result['next_cursor'];
        } while ($result['has_more']);

        $this->plaidConnection->update(['cursor' => $cursor, 'status' => 'active']);
        $this->updateBalances($plaid);

        Log::info('Plaid sync complete', [
            'connection_id' => $this->plaidConnection->id,
            'institution' => $this->plaidConnection->institution_name,
        ]);
    }

    private function processAdded(array $transactions): void
    {
        foreach ($transactions as $txn) {
            $account = Account::where('plaid_account_id', $txn['account_id'])->first();
            if (!$account) continue;

            Transaction::updateOrCreate(
                ['plaid_transaction_id' => $txn['transaction_id']],
                [
                    'account_id' => $account->id,
                    'amount' => abs($txn['amount']),
                    'date' => $txn['date'],
                    'merchant_name' => $txn['merchant_name'] ?? null,
                    'description' => $txn['name'],
                    'plaid_category' => $txn['personal_finance_category']['primary'] ?? null,
                    'needs_review' => true,
                ]
            );
        }
    }

    private function processModified(array $transactions): void
    {
        foreach ($transactions as $txn) {
            $existing = Transaction::where('plaid_transaction_id', $txn['transaction_id'])->first();
            if (!$existing) continue;

            $existing->update([
                'amount' => abs($txn['amount']),
                'date' => $txn['date'],
                'merchant_name' => $txn['merchant_name'] ?? null,
                'description' => $txn['name'],
                'plaid_category' => $txn['personal_finance_category']['primary'] ?? null,
            ]);
        }
    }

    private function processRemoved(array $transactions): void
    {
        $ids = array_column($transactions, 'transaction_id');
        if (!empty($ids)) {
            Transaction::whereIn('plaid_transaction_id', $ids)->delete();
        }
    }

    private function updateBalances(PlaidService $plaid): void
    {
        $accounts = $plaid->getAccounts($this->plaidConnection->access_token);
        foreach ($accounts as $plaidAccount) {
            Account::where('plaid_account_id', $plaidAccount['account_id'])->update([
                'current_balance' => $plaidAccount['balances']['current'] ?? 0,
                'available_balance' => $plaidAccount['balances']['available'] ?? 0,
                'last_synced_at' => now(),
            ]);
        }
    }
}
