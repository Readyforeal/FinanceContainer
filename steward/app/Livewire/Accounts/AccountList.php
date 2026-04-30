<?php

namespace App\Livewire\Accounts;

use App\Models\Account;
use App\Models\PlaidConnection;
use Livewire\Attributes\On;
use Livewire\Component;

class AccountList extends Component
{
    public function getConnectionsProperty()
    {
        return PlaidConnection::with('accounts')->get();
    }

    public function getAccountsProperty()
    {
        return Account::all();
    }

    #[On('plaid-connected')]
    public function refresh(): void
    {
        // Computed properties re-evaluate automatically; this triggers re-render
    }

    public function render()
    {
        return view('livewire.accounts.account-list', [
            'connections' => $this->connections,
            'accounts' => $this->accounts,
        ]);
    }
}
