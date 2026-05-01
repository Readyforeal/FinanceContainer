<?php

use App\Jobs\CategorizationJob;
use App\Models\Account;
use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

new class extends Component {
    use WithFileUploads;

    #[Validate('required|file|mimes:csv,txt|max:10240')]
    public $csvFile = null;

    public ?int $accountId = null;
    public string $importStatus = '';
    public int $importedCount = 0;
    public int $skippedCount = 0;
    public array $importErrors = [];

    public function import(): void
    {
        $this->validate();
        $this->importStatus = 'processing';
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->importErrors = [];

        if (! $this->accountId) {
            $this->importErrors[] = 'Please select an account.';
            $this->importStatus = 'error';
            return;
        }

        $account = Account::find($this->accountId);
        if (! $account) {
            $this->importErrors[] = 'Account not found.';
            $this->importStatus = 'error';
            return;
        }

        $path = $this->csvFile->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            $this->importErrors[] = 'Could not read the CSV file.';
            $this->importStatus = 'error';
            return;
        }

        // Read header row
        $header = fgetcsv($handle);
        if (! $header) {
            $this->importErrors[] = 'CSV file is empty.';
            $this->importStatus = 'error';
            fclose($handle);
            return;
        }

        // Normalize headers to lowercase
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        // Detect column mapping
        $mapping = $this->detectColumns($header);

        if (! $mapping['date'] || ! $mapping['amount']) {
            $this->importErrors[] = 'Could not detect required columns. CSV must have at least a date and amount column.';
            $this->importErrors[] = 'Detected headers: ' . implode(', ', $header);
            $this->importStatus = 'error';
            fclose($handle);
            return;
        }

        $newTransactionIds = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count($row) < 2) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), ''));

            $date = $this->parseDate($data[$mapping['date']] ?? '');
            $amount = $this->parseAmount($data[$mapping['amount']] ?? '', $data[$mapping['debit']] ?? '', $data[$mapping['credit']] ?? '');
            $description = trim($data[$mapping['description']] ?? $data[$mapping['memo']] ?? '');
            $merchant = trim($data[$mapping['merchant']] ?? '');

            if (! $date || $amount === null) {
                $this->skippedCount++;
                continue;
            }

            // Skip zero-amount transactions
            if (abs($amount) < 0.01) {
                $this->skippedCount++;
                continue;
            }

            // Generate a unique ID from the transaction data to prevent duplicates
            $uniqueId = 'csv_' . md5($account->id . $date . $amount . $description);

            // Skip if already imported
            if (Transaction::where('plaid_transaction_id', $uniqueId)->exists()) {
                $this->skippedCount++;
                continue;
            }

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'plaid_transaction_id' => $uniqueId,
                'amount' => abs($amount),
                'date' => $date,
                'merchant_name' => $merchant ?: null,
                'description' => $description ?: 'No description',
                'needs_review' => true,
            ]);

            $newTransactionIds[] = $transaction->id;
            $this->importedCount++;
        }

        fclose($handle);

        // Update account balance from latest transactions
        $totalSpent = Transaction::where('account_id', $account->id)->sum('amount');
        $account->update(['last_synced_at' => now()]);

        // Dispatch categorization for new transactions
        if (! empty($newTransactionIds)) {
            CategorizationJob::dispatch($newTransactionIds);
        }

        $this->importStatus = 'success';
        $this->csvFile = null;

        $this->dispatch('transactions-imported');
    }

    private function detectColumns(array $headers): array
    {
        $mapping = [
            'date' => null,
            'amount' => null,
            'description' => null,
            'merchant' => null,
            'memo' => null,
            'debit' => null,
            'credit' => null,
        ];

        foreach ($headers as $h) {
            if (preg_match('/\b(date|posted|trans.*date|transaction.*date)\b/i', $h)) {
                $mapping['date'] = $h;
            } elseif (preg_match('/\b(amount|total)\b/i', $h) && ! $mapping['amount']) {
                $mapping['amount'] = $h;
            } elseif (preg_match('/\b(desc|description|narrative|details|transaction.*desc)\b/i', $h)) {
                $mapping['description'] = $h;
            } elseif (preg_match('/\b(merchant|payee|name)\b/i', $h)) {
                $mapping['merchant'] = $h;
            } elseif (preg_match('/\b(memo|note|reference)\b/i', $h)) {
                $mapping['memo'] = $h;
            } elseif (preg_match('/\b(debit|withdrawal)\b/i', $h)) {
                $mapping['debit'] = $h;
            } elseif (preg_match('/\b(credit|deposit)\b/i', $h)) {
                $mapping['credit'] = $h;
            }
        }

        // If no single amount column but we have debit/credit, we'll use those
        if (! $mapping['amount'] && ($mapping['debit'] || $mapping['credit'])) {
            $mapping['amount'] = '__computed__';
        }

        // Fall back: if no description found, try the first text-like column
        if (! $mapping['description']) {
            foreach ($headers as $h) {
                if ($h !== $mapping['date'] && $h !== $mapping['amount'] && $h !== $mapping['debit'] && $h !== $mapping['credit']) {
                    $mapping['description'] = $h;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) return null;

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function parseAmount(string $amount, string $debit, string $credit): ?float
    {
        // If we have a single amount column
        if ($amount && $amount !== '__computed__') {
            $cleaned = preg_replace('/[^0-9.\-]/', '', $amount);
            return $cleaned !== '' ? (float) $cleaned : null;
        }

        // Compute from debit/credit columns
        $debitVal = (float) preg_replace('/[^0-9.]/', '', $debit);
        $creditVal = (float) preg_replace('/[^0-9.]/', '', $credit);

        if ($debitVal > 0) return $debitVal;
        if ($creditVal > 0) return $creditVal;

        return null;
    }

    public function with(): array
    {
        return [
            'accounts' => Account::all(),
        ];
    }
};
?>

<div>
    {{-- Import status messages --}}
    @if ($importStatus === 'success')
        <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4 mb-4">
            <div class="flex items-center gap-3">
                <x-lucide-check-circle class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                <div>
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">Import complete</p>
                    <p class="text-sm text-green-600 dark:text-green-400">{{ $importedCount }} transactions imported{{ $skippedCount > 0 ? ", {$skippedCount} skipped (duplicates or invalid)" : '' }}. Categorization is running in the background.</p>
                </div>
            </div>
        </div>
    @endif

    @if ($importStatus === 'error')
        <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 mb-4">
            <div class="flex items-start gap-3">
                <x-lucide-alert-circle class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">Import failed</p>
                    @foreach ($importErrors as $error)
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Import form --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
        <div class="flex items-center gap-3 mb-4">
            <x-lucide-upload class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Import Transactions from CSV</h3>
        </div>

        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-4">
            Download a CSV from your bank's website and upload it here. Most banks support CSV export from transaction history.
            The importer auto-detects column formats from most major banks.
        </p>

        <form wire:submit="import" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Account</label>
                    <select wire:model="accountId" class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-zinc-200">
                        <option value="">Select account...</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->type->value }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">CSV File</label>
                    <input type="file" wire:model="csvFile" accept=".csv,.txt"
                        class="w-full text-sm text-zinc-500 dark:text-zinc-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-zinc-100 dark:file:bg-zinc-800 file:text-zinc-700 dark:file:text-zinc-300 hover:file:bg-zinc-200 dark:hover:file:bg-zinc-700 file:cursor-pointer file:transition-colors" />
                    @error('csvFile') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                    wire:loading.attr="disabled"
                    @disabled(! $csvFile || ! $accountId)
                >
                    <span wire:loading.remove wire:target="import">
                        <x-lucide-upload class="w-4 h-4" />
                    </span>
                    <span wire:loading wire:target="import">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                    </span>
                    Import Transactions
                </button>

                <span wire:loading wire:target="csvFile" class="text-xs text-zinc-400">Uploading file...</span>
            </div>
        </form>
    </div>
</div>
