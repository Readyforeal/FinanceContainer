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

        // Peek at the first row to detect if there are headers
        $firstRow = fgetcsv($handle);
        if (! $firstRow || count($firstRow) < 2) {
            $this->importErrors[] = 'CSV file is empty or has too few columns.';
            $this->importStatus = 'error';
            fclose($handle);
            return;
        }

        $hasHeaders = $this->detectIfHeaders($firstRow);
        $rows = [];

        if ($hasHeaders) {
            // First row is headers — normalize and use them
            $header = array_map(fn ($h) => strtolower(trim($h)), $firstRow);
            $mapping = $this->detectColumns($header);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 2) continue;
                $data = array_combine($header, array_pad($row, count($header), ''));
                $rows[] = [
                    'date' => $data[$mapping['date']] ?? '',
                    'amount' => $this->parseAmount($data[$mapping['amount']] ?? '', $data[$mapping['debit']] ?? '', $data[$mapping['credit']] ?? ''),
                    'description' => trim($data[$mapping['description']] ?? $data[$mapping['memo']] ?? ''),
                    'merchant' => trim($data[$mapping['merchant']] ?? ''),
                    'txn_id' => null,
                ];
            }
        } else {
            // No headers — detect column layout by position
            // Rewind: process the first row as data
            rewind($handle);

            $colCount = count($firstRow);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 2) continue;

                $parsed = $this->parseHeaderlessRow($row, $colCount);
                if ($parsed) {
                    $rows[] = $parsed;
                }
            }
        }

        fclose($handle);

        if (empty($rows)) {
            $this->importErrors[] = 'No valid transactions found in the file.';
            $this->importStatus = 'error';
            return;
        }

        $newTransactionIds = [];

        foreach ($rows as $row) {
            $date = $this->parseDate($row['date']);
            $amount = $row['amount'];
            $description = $row['description'] ?: $row['merchant'] ?: 'No description';
            $merchant = $row['merchant'] ?: null;

            if (! $date || $amount === null || abs($amount) < 0.01) {
                $this->skippedCount++;
                continue;
            }

            // Use bank's transaction ID if available, otherwise hash the content
            $uniqueId = $row['txn_id']
                ? 'csv_' . $row['txn_id']
                : 'csv_' . md5($account->id . $date . $amount . $description);

            if (Transaction::where('plaid_transaction_id', $uniqueId)->exists()) {
                $this->skippedCount++;
                continue;
            }

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'plaid_transaction_id' => $uniqueId,
                'amount' => $amount,
                'date' => $date,
                'merchant_name' => $merchant,
                'description' => $description,
                'needs_review' => true,
            ]);

            $newTransactionIds[] = $transaction->id;
            $this->importedCount++;
        }

        $account->update(['last_synced_at' => now()]);

        if (! empty($newTransactionIds)) {
            CategorizationJob::dispatch($newTransactionIds);
        }

        $this->importStatus = 'success';
        $this->csvFile = null;

        $this->dispatch('transactions-imported');
    }

    /**
     * Detect if the first row looks like headers (contains non-numeric, non-date text)
     */
    private function detectIfHeaders(array $row): bool
    {
        $textColumns = 0;
        foreach ($row as $val) {
            $val = trim($val);
            // If it looks like a date or number, it's probably data
            if ($this->parseDate($val) !== null) continue;
            if (is_numeric(preg_replace('/[^0-9.\-]/', '', $val)) && preg_match('/[\d]/', $val)) continue;
            // If it's a short text string with no numbers, probably a header
            if (preg_match('/^[a-zA-Z\s_\-\/]+$/', $val)) {
                $textColumns++;
            }
        }

        return $textColumns >= 2;
    }

    /**
     * Parse a row without headers by detecting column positions.
     * Supports common bank formats:
     * - 4 columns: date, amount, transaction_id, merchant/description
     * - 3 columns: date, amount, description
     * - 5+ columns: date, description, amount, ... (try multiple layouts)
     */
    private function parseHeaderlessRow(array $row, int $colCount): ?array
    {
        $row = array_map('trim', $row);

        // 4 columns: date, amount (+/-), transaction_id, merchant
        if ($colCount === 4) {
            return [
                'date' => $row[0],
                'amount' => $this->parseSingleAmount($row[1]),
                'txn_id' => $row[2],
                'description' => $row[3],
                'merchant' => $row[3],
            ];
        }

        // 3 columns: date, amount, description
        if ($colCount === 3) {
            return [
                'date' => $row[0],
                'amount' => $this->parseSingleAmount($row[1]),
                'txn_id' => null,
                'description' => $row[2],
                'merchant' => $row[2],
            ];
        }

        // 5+ columns: try to find date in first column, amount in second or third
        // and description in a later column
        if ($colCount >= 5) {
            $date = $row[0];
            $amount = null;
            $descriptionParts = [];

            for ($i = 1; $i < $colCount; $i++) {
                $tryAmount = $this->parseSingleAmount($row[$i]);
                if ($tryAmount !== null && $amount === null) {
                    $amount = $tryAmount;
                } else {
                    $val = trim($row[$i]);
                    if ($val !== '' && ! is_numeric(str_replace([',', '$'], '', $val))) {
                        $descriptionParts[] = $val;
                    }
                }
            }

            return [
                'date' => $date,
                'amount' => $amount,
                'txn_id' => null,
                'description' => implode(' — ', $descriptionParts) ?: 'No description',
                'merchant' => $descriptionParts[0] ?? '',
            ];
        }

        return null;
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

        if (! $mapping['amount'] && ($mapping['debit'] || $mapping['credit'])) {
            $mapping['amount'] = '__computed__';
        }

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

    private function parseSingleAmount(string $value): ?float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', trim($value));
        return ($cleaned !== '' && is_numeric($cleaned)) ? (float) $cleaned : null;
    }

    private function parseAmount(string $amount, string $debit, string $credit): ?float
    {
        if ($amount && $amount !== '__computed__') {
            return $this->parseSingleAmount($amount);
        }

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
            Download a CSV from your bank's website and upload it here. Works with or without headers.
            Supports most bank formats including headerless files (date, amount, ID, merchant).
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
