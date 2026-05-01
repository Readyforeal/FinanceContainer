<?php

use App\Enums\AccountType;
use App\Enums\PlaidConnectionStatus;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Services\PlaidService;
use Livewire\Component;

new class extends Component {
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
};
?>

<div>
    @if ($error)
        <div class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded-lg mb-4">
            {{ $error }}
        </div>
    @endif

    @if (! $linkToken)
        <button
            wire:click="createLinkToken"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium px-4 py-2 rounded-lg transition-colors"
        >
            <flux:icon.plus class="w-4 h-4" />
            <span wire:loading.remove>Connect Bank Account</span>
            <span wire:loading>Connecting...</span>
        </button>
    @else
        <div class="text-sm text-zinc-500 dark:text-zinc-400">Launching Plaid...</div>
    @endif

    @if ($linkToken)
        <script>
            (function () {
                var script = document.createElement('script');
                script.src = 'https://cdn.plaid.com/link/v2/stable/link-initialize.js';
                script.onload = function () {
                    var handler = Plaid.create({
                        token: @json($linkToken),
                        onSuccess: function (publicToken, metadata) {
                            @this.call('onSuccess', publicToken, metadata);
                        },
                        onExit: function (err, metadata) {
                            if (err) {
                                console.error('Plaid exit error:', err);
                            }
                            @this.set('connecting', false);
                            @this.set('linkToken', null);
                        },
                        onLoad: function () {
                            handler.open();
                        },
                    });
                };
                document.head.appendChild(script);
            })();
        </script>
    @endif
</div>
