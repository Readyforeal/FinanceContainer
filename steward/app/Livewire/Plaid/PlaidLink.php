<?php

namespace App\Livewire\Plaid;

use App\Enums\AccountType;
use App\Enums\PlaidConnectionStatus;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Services\PlaidService;
use Livewire\Component;

class PlaidLink extends Component
{
    public ?string $linkToken = null;
    public bool $connecting = false;
    public ?string $error = null;

    public function createLinkToken(): void
    {
        $this->error = null;
        $this->connecting = true;

        try {
            $plaidService = app(PlaidService::class);
            $result = $plaidService->createLinkToken((string) auth()->id());
            $this->linkToken = $result['link_token'] ?? null;

            if (! $this->linkToken) {
                $this->error = 'Failed to create link token.';
                $this->connecting = false;
            }
        } catch (\Exception $e) {
            $this->error = 'Unable to connect to Plaid: ' . $e->getMessage();
            $this->connecting = false;
        }
    }

    public function onSuccess(string $publicToken, array $metadata): void
    {
        $this->error = null;

        try {
            $plaidService = app(PlaidService::class);

            $exchange = $plaidService->exchangePublicToken($publicToken);

            $accessToken = $exchange['access_token'];
            $itemId = $exchange['item_id'];
            $institutionName = $metadata['institution']['name'] ?? 'Unknown Institution';

            $connection = PlaidConnection::create([
                'access_token' => $accessToken,
                'item_id' => $itemId,
                'institution_name' => $institutionName,
                'status' => PlaidConnectionStatus::Active,
            ]);

            $accounts = $plaidService->getAccounts($accessToken);

            foreach ($accounts as $accountData) {
                $subtype = $accountData['subtype'] ?? '';
                $type = match ($subtype) {
                    'savings' => AccountType::Savings,
                    default => AccountType::Checking,
                };

                Account::create([
                    'plaid_connection_id' => $connection->id,
                    'plaid_account_id' => $accountData['account_id'],
                    'name' => $accountData['name'],
                    'type' => $type,
                    'current_balance' => $accountData['balances']['current'] ?? 0,
                    'available_balance' => $accountData['balances']['available'] ?? 0,
                    'last_synced_at' => now(),
                ]);
            }

            $this->linkToken = null;
            $this->connecting = false;
            $this->dispatch('plaid-connected');
        } catch (\Exception $e) {
            $this->error = 'Failed to connect account: ' . $e->getMessage();
            $this->connecting = false;
        }
    }

    public function render()
    {
        return view('livewire.plaid.plaid-link');
    }
}
