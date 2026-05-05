<?php

use App\Enums\BillFrequency;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\IncomeSource;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $viewingMonth = '';
    public string $view = 'list';

    public string $formName = '';
    public string $formPayee = '';
    public string $formMerchantPattern = '';
    public string $formAmount = '';
    public bool $formIsFixed = false;
    public int $formDueDay = 1;
    public string $formFrequency = 'monthly';
    public bool $formIsAutopay = false;
    public ?int $formAccountId = null;
    public ?int $formCategoryId = null;
    public string $formNotes = '';

    public ?int $editingBillId = null;

    public function mount(): void
    {
        $this->viewingMonth = now()->format('Y-m');
        $this->js("setTimeout(() => window.dispatchEvent(new CustomEvent('set-dock-action', { detail: { label: 'Add Bill' } })), 100)");
    }

    public function previousMonth(): void
    {
        $this->viewingMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->viewingMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth)->addMonth()->format('Y-m');
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['list', 'calendar']) ? $view : 'list';
    }

    #[On('dock-action')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formAccountId = Account::first()?->id;
        $this->modal('bill-editor')->show();
    }

    public function openEdit(int $id): void
    {
        $bill = Bill::findOrFail($id);
        $this->editingBillId = $bill->id;
        $this->formName = $bill->name;
        $this->formPayee = $bill->payee ?? '';
        $this->formMerchantPattern = $bill->merchant_pattern ?? '';
        $this->formAmount = $bill->amount ? (string) $bill->amount : '';
        $this->formIsFixed = $bill->is_fixed;
        $this->formDueDay = $bill->due_day;
        $this->formFrequency = $bill->frequency->value;
        $this->formIsAutopay = $bill->is_autopay;
        $this->formAccountId = $bill->account_id;
        $this->formCategoryId = $bill->category_id;
        $this->formNotes = $bill->notes ?? '';
        $this->modal('bill-editor')->show();
    }

    public function save(): void
    {
        $this->validate([
            'formName' => ['required', 'string', 'max:255'],
            'formPayee' => ['required', 'string', 'max:255'],
            'formMerchantPattern' => ['required', 'string', 'max:255'],
            'formDueDay' => ['required', 'integer', 'between:1,31'],
            'formAccountId' => ['required', 'exists:accounts,id'],
        ]);

        $data = [
            'name' => $this->formName,
            'payee' => $this->formPayee,
            'merchant_pattern' => $this->formMerchantPattern,
            'amount' => $this->formAmount !== '' ? $this->formAmount : null,
            'is_fixed' => $this->formIsFixed,
            'due_day' => $this->formDueDay,
            'frequency' => $this->formFrequency,
            'is_autopay' => $this->formIsAutopay,
            'account_id' => $this->formAccountId,
            'category_id' => $this->formCategoryId,
            'notes' => $this->formNotes ?: null,
        ];

        if ($this->editingBillId) {
            Bill::findOrFail($this->editingBillId)->update($data);
        } else {
            Bill::create($data);
        }

        $this->modal('bill-editor')->close();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Bill::findOrFail($id)->delete();
        $this->modal('bill-editor')->close();
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingBillId = null;
        $this->formName = '';
        $this->formPayee = '';
        $this->formMerchantPattern = '';
        $this->formAmount = '';
        $this->formIsFixed = false;
        $this->formDueDay = 1;
        $this->formFrequency = 'monthly';
        $this->formIsAutopay = false;
        $this->formAccountId = null;
        $this->formCategoryId = null;
        $this->formNotes = '';
    }

    public function with(): array
    {
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth);

        $bills = Bill::with(['account', 'category'])
            ->where('is_active', true)
            ->orderBy('due_day')
            ->get();

        $billStatuses = [];
        $billPayments = [];
        foreach ($bills as $bill) {
            $bill->computed_status = $bill->statusForMonth($monthDate);
            $billStatuses[$bill->id] = $bill->computed_status;
            if ($billStatuses[$bill->id] === 'paid') {
                $periodStart = $monthDate->copy()->startOfMonth();
                $periodEnd = $monthDate->copy()->endOfMonth();
                $payment = $bill->matchingTransaction($periodStart, $periodEnd);
                $bill->matched_payment = $payment;
                $billPayments[$bill->id] = $payment;
            }
        }

        // Calendar grid data
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();
        $startDayOfWeek = $startOfMonth->dayOfWeek; // 0=Sunday
        $daysInMonth = $endOfMonth->day;

        $calendarDays = [];
        for ($i = 0; $i < $startDayOfWeek; $i++) {
            $calendarDays[] = null;
        }
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $startOfMonth->copy()->addDays($day - 1);
            $dayBills = $bills->filter(fn ($b) => $b->due_day === $day);
            $calendarDays[] = [
                'day' => $day,
                'date' => $date,
                'bills' => $dayBills,
                'isToday' => $date->isToday(),
            ];
        }

        // Payday indicators
        $paydays = IncomeSource::where('is_active', true)
            ->whereNotNull('next_pay_date')
            ->get()
            ->flatMap(function ($source) use ($startOfMonth, $endOfMonth) {
                $dates = [];
                $date = $source->next_pay_date->copy();
                while ($date->gt($endOfMonth)) {
                    $date = match($source->frequency) {
                        'weekly' => $date->subWeek(),
                        'biweekly' => $date->subWeeks(2),
                        'monthly' => $date->subMonth(),
                        default => $date->subMonth(),
                    };
                }
                while ($date->lt($startOfMonth)) {
                    $date = match($source->frequency) {
                        'weekly' => $date->addWeek(),
                        'biweekly' => $date->addWeeks(2),
                        'monthly' => $date->addMonth(),
                        default => $date->addMonth(),
                    };
                }
                while ($date->lte($endOfMonth)) {
                    if ($date->gte($startOfMonth)) {
                        $dates[] = $date->day;
                    }
                    $date = match($source->frequency) {
                        'weekly' => $date->addWeek(),
                        'biweekly' => $date->addWeeks(2),
                        'monthly' => $date->addMonth(),
                        default => $date->addMonth(),
                    };
                }
                return $dates;
            })->unique()->values()->toArray();

        $accounts = Account::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $frequencies = BillFrequency::cases();

        return compact('bills', 'billStatuses', 'billPayments', 'accounts', 'categories', 'frequencies', 'monthDate', 'calendarDays', 'paydays');
    }
};
?>

<div>
    {{-- Month navigation --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-3 justify-between md:justify-start">
                <flux:button variant="subtle" square icon="chevron-left" wire:click="previousMonth" />
                <flux:heading size="xl">{{ $monthDate->format('F Y') }}</flux:heading>
                <flux:button variant="subtle" square icon="chevron-right" wire:click="nextMonth" />
            </div>
            <flux:text size="sm" class="mt-1 text-center md:text-left">
                {{ $bills->count() }} active {{ Str::plural('bill', $bills->count()) }}
            </flux:text>
        </div>
        <div class="hidden lg:block">
            <flux:button wire:click="openCreate" variant="primary" icon="plus">Add Bill</flux:button>
        </div>
    </div>

    {{-- View toggle --}}
    <div class="flex gap-1 mb-4">
        <flux:button wire:click="setView('list')" size="sm" :variant="$view === 'list' ? 'primary' : 'subtle'">List</flux:button>
        <flux:button wire:click="setView('calendar')" size="sm" :variant="$view === 'calendar' ? 'primary' : 'subtle'">Calendar</flux:button>
    </div>

    {{-- Calendar view --}}
    @if ($view === 'calendar')
        <flux:card class="!p-3">
            {{-- Day of week headers --}}
            <div class="grid grid-cols-7 gap-1 mb-2">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="text-center">
                        <flux:text size="xs" class="font-medium">{{ $dayName }}</flux:text>
                    </div>
                @endforeach
            </div>

            {{-- Calendar grid --}}
            <div class="grid grid-cols-7 gap-1">
                @foreach ($calendarDays as $calDay)
                    @if ($calDay === null)
                        <div></div>
                    @else
                        <div class="min-h-16 lg:min-h-20 p-1.5 rounded-lg border {{ $calDay['isToday'] ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-950/20' : 'border-zinc-100 dark:border-zinc-800' }}">
                            <div class="flex items-center justify-between mb-1">
                                <flux:text size="xs" class="{{ $calDay['isToday'] ? 'font-bold text-blue-600 dark:text-blue-400' : '' }}">
                                    {{ $calDay['day'] }}
                                </flux:text>
                                @if (in_array($calDay['day'], $paydays))
                                    <span class="w-2 h-2 rounded-full bg-blue-500" title="Payday"></span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-0.5">
                                @foreach ($calDay['bills'] as $bill)
                                    @php
                                        $status = $bill->computed_status ?? 'upcoming';
                                        $dotColor = match($status) {
                                            'paid' => 'bg-green-500',
                                            'overdue' => 'bg-red-500',
                                            'due_soon' => 'bg-amber-500',
                                            default => 'bg-zinc-400',
                                        };
                                    @endphp
                                    <span class="w-2 h-2 rounded-full {{ $dotColor }}" title="{{ $bill->name }}: {{ $status }}"></span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <flux:text size="xs">Paid</flux:text>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <flux:text size="xs">Overdue</flux:text>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <flux:text size="xs">Due Soon</flux:text>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                    <flux:text size="xs">Upcoming</flux:text>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <flux:text size="xs">Payday</flux:text>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Bill list --}}
    @if ($view === 'list')
    <flux:card class="!p-0 overflow-hidden">
        {{-- Desktop table --}}
        <div class="hidden lg:block">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Payee</flux:table.column>
                    <flux:table.column>Amount</flux:table.column>
                    <flux:table.column>Due Date</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column class="text-right">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($bills as $bill)
                        @php
                            $status = $billStatuses[$bill->id];
                            $payment = $billPayments[$bill->id] ?? null;
                            $ordinal = match(true) {
                                in_array($bill->due_day % 10, [1]) && $bill->due_day !== 11 => $bill->due_day . 'st',
                                in_array($bill->due_day % 10, [2]) && $bill->due_day !== 12 => $bill->due_day . 'nd',
                                in_array($bill->due_day % 10, [3]) && $bill->due_day !== 13 => $bill->due_day . 'rd',
                                default => $bill->due_day . 'th',
                            };
                            $statusColor = match($status) {
                                'paid' => 'green',
                                'overdue' => 'red',
                                'due_soon' => 'amber',
                                default => 'zinc',
                            };
                            $statusLabel = match($status) {
                                'paid' => 'Paid',
                                'overdue' => 'Overdue',
                                'due_soon' => 'Due Soon',
                                default => 'Upcoming',
                            };
                        @endphp
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $bill->name }}</span>
                                    @if ($bill->is_autopay)
                                        <flux:badge size="sm" color="sky">Auto</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $bill->payee }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($bill->amount)
                                    {{ $bill->is_fixed ? '' : '~' }}${{ number_format($bill->amount, 2) }}
                                @else
                                    <flux:text size="sm" class="text-zinc-400">Variable</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $ordinal }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$statusColor">
                                    {{ $statusLabel }}
                                    @if ($payment)
                                        (${{ number_format(abs($payment->amount), 2) }})
                                    @endif
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button variant="subtle" size="xs" icon="pencil" wire:click="openEdit({{ $bill->id }})" />
                                    <flux:button variant="subtle" size="xs" icon="trash-2" wire:click="delete({{ $bill->id }})" wire:confirm="Delete this bill?" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center">
                                <flux:text size="sm">No active bills yet. Add one to get started.</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{-- Mobile list --}}
        <div class="lg:hidden">
            @forelse ($bills as $bill)
                @php
                    $status = $billStatuses[$bill->id];
                    $payment = $billPayments[$bill->id] ?? null;
                    $ordinal = match(true) {
                        in_array($bill->due_day % 10, [1]) && $bill->due_day !== 11 => $bill->due_day . 'st',
                        in_array($bill->due_day % 10, [2]) && $bill->due_day !== 12 => $bill->due_day . 'nd',
                        in_array($bill->due_day % 10, [3]) && $bill->due_day !== 13 => $bill->due_day . 'rd',
                        default => $bill->due_day . 'th',
                    };
                    $statusColor = match($status) {
                        'paid' => 'green',
                        'overdue' => 'red',
                        'due_soon' => 'amber',
                        default => 'zinc',
                    };
                    $statusLabel = match($status) {
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'due_soon' => 'Due Soon',
                        default => 'Upcoming',
                    };
                @endphp
                <div wire:click="openEdit({{ $bill->id }})" class="p-4 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 cursor-pointer active:bg-zinc-50 dark:active:bg-zinc-800/50">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon.calendar-days variant="mini" class="shrink-0 text-zinc-400 dark:text-zinc-500" />
                            <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $bill->name }}</span>
                            @if ($bill->is_autopay)
                                <flux:badge size="sm" color="sky">Auto</flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($bill->amount)
                                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $bill->is_fixed ? '' : '~' }}${{ number_format($bill->amount, 2) }}
                                </span>
                            @else
                                <flux:text size="sm" class="text-zinc-400">Variable</flux:text>
                            @endif
                            <flux:icon.chevron-right variant="mini" class="text-zinc-300 dark:text-zinc-600" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-1 ml-7">
                        <flux:text size="xs">{{ $bill->payee }}</flux:text>
                        <flux:text size="xs">Due {{ $ordinal }}</flux:text>
                        <flux:badge size="sm" :color="$statusColor">
                            {{ $statusLabel }}
                            @if ($payment)
                                (${{ number_format(abs($payment->amount), 2) }})
                            @endif
                        </flux:badge>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center">
                    <flux:text size="sm">No active bills yet. Add one to get started.</flux:text>
                </div>
            @endforelse
        </div>
    </flux:card>
    @endif

    {{-- Bill editor modal --}}
    <flux:modal name="bill-editor" class="w-full md:w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingBillId ? 'Edit Bill' : 'Add Bill' }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $editingBillId ? 'Update this bill\'s details.' : 'Add a new recurring bill to track.' }}
                </flux:text>
            </div>

            <flux:input wire:model="formName" label="Name" placeholder="e.g. Electric Bill" />
            @error('formName') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <flux:input wire:model="formPayee" label="Payee" placeholder="e.g. Duke Energy" />
            @error('formPayee') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <flux:input wire:model="formMerchantPattern" label="Merchant Pattern" placeholder="e.g. DUKE ENERGY" description="Used to match transactions automatically." />
            @error('formMerchantPattern') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:input wire:model="formAmount" type="number" label="Amount" min="0" step="0.01" placeholder="0.00" />
                </div>
                <div class="flex items-end pb-2">
                    <flux:switch wire:model="formIsFixed" label="Fixed Amount" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="formDueDay" label="Due Day">
                    @for ($d = 1; $d <= 31; $d++)
                        <flux:select.option value="{{ $d }}">{{ $d }}</flux:select.option>
                    @endfor
                </flux:select>

                <flux:select wire:model="formFrequency" label="Frequency">
                    @foreach ($frequencies as $freq)
                        <flux:select.option value="{{ $freq->value }}">{{ $freq->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:switch wire:model="formIsAutopay" label="Auto-pay" />

            <flux:select wire:model="formAccountId" label="Account" placeholder="Select account...">
                @foreach ($accounts as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('formAccountId') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <flux:select wire:model="formCategoryId" label="Category" placeholder="None">
                <flux:select.option value="">-- None --</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="formNotes" label="Notes" placeholder="Optional notes..." rows="2" />

            <div class="flex justify-between gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="save" variant="primary">
                    {{ $editingBillId ? 'Update Bill' : 'Add Bill' }}
                </flux:button>
            </div>

            @if ($editingBillId)
                <flux:button wire:click="delete({{ $editingBillId }})" wire:confirm="Are you sure you want to delete this bill?" variant="danger" class="w-full">
                    Delete Bill
                </flux:button>
            @endif
        </div>
    </flux:modal>
</div>
