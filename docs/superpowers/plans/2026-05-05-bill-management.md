# Bill Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add bill tracking with merchant-pattern payment detection, calendar view, upcoming list, and AI context integration.

**Architecture:** New `Bill` model with migration, enum, factory, and seeder. New Livewire page component with calendar and list views inside a tabbed layout. Bills auto-detect payments by matching transaction merchants. AI gets bill context for proactive coaching.

**Tech Stack:** Laravel 13, Livewire 4, Flux UI, PostgreSQL, existing ApexCharts for any visualizations.

---

### Task 1: Create BillFrequency Enum, Migration, Model, and Factory

**Files:**
- Create: `app/Enums/BillFrequency.php`
- Create: `database/migrations/2026_05_05_000000_create_bills_table.php`
- Create: `app/Models/Bill.php`
- Create: `database/factories/BillFactory.php`

- [ ] **Step 1: Create the BillFrequency enum**

```php
// app/Enums/BillFrequency.php
<?php

namespace App\Enums;

enum BillFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Annually = 'annually';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';

    public function label(): string
    {
        return match($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnually => 'Semi-Annually',
            self::Annually => 'Annually',
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
        };
    }
}
```

- [ ] **Step 2: Create the migration**

```bash
php artisan make:migration create_bills_table
```

Migration content:

```php
Schema::create('bills', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('payee');
    $table->string('merchant_pattern');
    $table->decimal('amount', 10, 2)->nullable();
    $table->boolean('is_fixed')->default(false);
    $table->integer('due_day');
    $table->string('frequency')->default('monthly');
    $table->boolean('is_autopay')->default(false);
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

- [ ] **Step 3: Create the Bill model**

```php
// app/Models/Bill.php
<?php

namespace App\Models;

use App\Enums\BillFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'payee', 'merchant_pattern', 'amount', 'is_fixed',
        'due_day', 'frequency', 'is_autopay', 'account_id',
        'category_id', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_fixed' => 'boolean',
            'is_autopay' => 'boolean',
            'is_active' => 'boolean',
            'frequency' => BillFrequency::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function dueDateForMonth(Carbon $month): Carbon
    {
        $day = min($this->due_day, $month->copy()->endOfMonth()->day);
        return $month->copy()->startOfMonth()->addDays($day - 1);
    }

    public function matchingTransaction(Carbon $periodStart, Carbon $periodEnd): ?Transaction
    {
        return Transaction::where('merchant_name', 'ilike', '%' . $this->merchant_pattern . '%')
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('amount', '<', 0)
            ->orderByDesc('date')
            ->first();
    }

    public function statusForMonth(Carbon $month): string
    {
        $dueDate = $this->dueDateForMonth($month);
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $payment = $this->matchingTransaction($periodStart, $periodEnd);

        if ($payment) {
            return 'paid';
        }

        if (now()->gt($dueDate) && now()->month === $month->month && now()->year === $month->year) {
            return 'overdue';
        }

        if (now()->diffInDays($dueDate, false) <= 5 && now()->diffInDays($dueDate, false) >= 0) {
            return 'due_soon';
        }

        return 'upcoming';
    }
}
```

- [ ] **Step 4: Create the factory**

```php
// database/factories/BillFactory.php
<?php

namespace Database\Factories;

use App\Enums\BillFrequency;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Bill',
            'payee' => $this->faker->company(),
            'merchant_pattern' => strtoupper($this->faker->word()),
            'amount' => $this->faker->randomFloat(2, 20, 500),
            'is_fixed' => $this->faker->boolean(70),
            'due_day' => $this->faker->numberBetween(1, 28),
            'frequency' => $this->faker->randomElement(BillFrequency::cases()),
            'is_autopay' => $this->faker->boolean(40),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 5: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add Bill model, migration, enum, and factory"
```

---

### Task 2: Create Bills Page with Upcoming List View

**Files:**
- Create: `resources/views/pages/bills.blade.php`
- Create: `resources/views/livewire/bills/bill-manager.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/layouts/app.blade.php` (sidebar)
- Modify: `resources/views/livewire/layout/mobile-dock.blade.php` (dock grid)

- [ ] **Step 1: Import the calendar-days Lucide icon**

```bash
php artisan flux:icon calendar-days
```

- [ ] **Step 2: Add the route**

Add to `routes/web.php` inside the auth middleware group:

```php
Route::livewire('/bills', 'pages::bills')->name('bills');
```

- [ ] **Step 3: Create the page component**

```blade
{{-- resources/views/pages/bills.blade.php --}}
<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Bills')] class extends Component {};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Bills</flux:heading>
        <flux:text class="mt-1">Track and manage your recurring bills.</flux:text>
    </div>

    <livewire:bills.bill-manager />
</div>
```

- [ ] **Step 4: Create the bill-manager Livewire component**

Create `resources/views/livewire/bills/bill-manager.blade.php` with:

**PHP class:**
- Properties: `$viewingMonth` (string Y-m), form fields for bill CRUD (`$formName`, `$formPayee`, `$formMerchantPattern`, `$formAmount`, `$formIsFixed`, `$formDueDay`, `$formFrequency`, `$formIsAutopay`, `$formAccountId`, `$formCategoryId`, `$formNotes`, `$editingBillId`)
- `mount()`: set `$viewingMonth` to current month, dispatch dock action
- `previousMonth()` / `nextMonth()`: navigate months
- `openCreate()`: reset form, open modal (with `#[On('dock-action')]`)
- `openEdit(int $id)`: load bill into form, open modal
- `save()`: validate and create/update bill
- `delete(int $id)`: delete bill with confirmation
- `with()`: return bills, accounts, categories, and computed bill statuses for the viewing month

**Template — Upcoming List section:**
- Shows bills for the viewing month sorted by due_day
- Each bill row: icon from category, name, payee, amount (or "~" prefix for variable), due date, status badge (paid=green, overdue=red, due_soon=amber, upcoming=zinc)
- Paid bills show actual amount from matched transaction
- Month navigation arrows (same pattern as budgets page)
- Desktop: `flux:table` layout
- Mobile: `flux:card !p-0` list layout (same as budgets/transactions)

**Template — Bill Editor Modal:**
- `flux:modal name="bill-editor" class="w-full md:w-2xl"`
- Fields: Name, Payee, Merchant Pattern, Amount (with is_fixed toggle), Due Day (select 1-31), Frequency (select), Auto-pay (switch), Account (select), Category (select), Notes (textarea)
- Cancel/Save justified between, Delete full-width below when editing

- [ ] **Step 5: Add to sidebar navigation**

In `resources/views/components/layouts/app.blade.php`, add after the Goals item:

```blade
<flux:sidebar.item icon="calendar-days" href="/bills" wire:navigate>Bills</flux:sidebar.item>
```

- [ ] **Step 6: Add to mobile dock grid**

In `resources/views/livewire/layout/mobile-dock.blade.php`, add to the `$allItems` array:

```php
['path' => '/bills', 'label' => 'Bills', 'icon' => 'calendar-days'],
```

- [ ] **Step 7: Verify page loads**

```bash
php artisan view:cache
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add bills page with upcoming list view and CRUD modal"
```

---

### Task 3: Add Calendar View

**Files:**
- Modify: `resources/views/livewire/bills/bill-manager.blade.php` (add calendar section to template)

- [ ] **Step 1: Add calendar rendering logic to the `with()` method**

Add to the `with()` method: build a calendar grid for the viewing month. For each day, collect bills due on that day with their status. Also collect paydays from IncomeSource next_pay_date data.

```php
// Build calendar data
$monthDate = Carbon::createFromFormat('Y-m', $this->viewingMonth);
$startOfMonth = $monthDate->copy()->startOfMonth();
$endOfMonth = $monthDate->copy()->endOfMonth();
$startDayOfWeek = $startOfMonth->dayOfWeek; // 0=Sunday
$daysInMonth = $endOfMonth->day;

$calendarDays = [];
// Pad with nulls for days before the 1st
for ($i = 0; $i < $startDayOfWeek; $i++) {
    $calendarDays[] = null;
}
// Fill actual days
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
```

- [ ] **Step 2: Add calendar grid to the template**

Above the upcoming list, add a calendar section. Use a 7-column CSS grid with day-of-week headers (S M T W T F S). Each cell shows the day number and colored dots for bills (green=paid, red=overdue, amber=due_soon, zinc=upcoming). Paydays show a blue dot.

The calendar should be inside a `flux:card` and show on both mobile and desktop.

- [ ] **Step 3: Add a tab toggle between Calendar and List views**

Add two `flux:button` toggles at the top (Calendar / List) that show/hide the respective sections using a `$view` property (default: 'list').

- [ ] **Step 4: Verify calendar renders**

```bash
php artisan view:cache
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add calendar view for bills with status indicators"
```

---

### Task 4: Add AI Context Integration

**Files:**
- Modify: `app/Services/FinancialContextBuilder.php`

- [ ] **Step 1: Add `buildBillsSection()` method**

```php
private function buildBillsSection(): string
{
    $bills = Bill::where('is_active', true)->orderBy('due_day')->get();

    if ($bills->isEmpty()) {
        return "BILLS (current month):\n  (no bills tracked)";
    }

    $now = now();
    $periodStart = $now->copy()->startOfMonth();
    $periodEnd = $now->copy()->endOfMonth();

    $lines = ['BILLS (current month):'];
    $totalUnpaid = 0;

    foreach ($bills as $bill) {
        $status = $bill->statusForMonth($now);
        $payment = $bill->matchingTransaction($periodStart, $periodEnd);

        $amountStr = $bill->is_fixed
            ? '$' . number_format((float) $bill->amount, 2)
            : '~$' . number_format((float) $bill->amount, 2);

        $dueDate = $bill->dueDateForMonth($now);
        $statusLabel = match($status) {
            'paid' => 'PAID on ' . $payment->date->format('M j') . ' ($' . number_format(abs((float) $payment->amount), 2) . ')',
            'overdue' => 'OVERDUE (was due ' . $dueDate->format('M j') . ')',
            'due_soon' => 'DUE SOON (' . $dueDate->format('M j') . ', ' . now()->diffInDays($dueDate) . ' days)',
            'upcoming' => 'due ' . $dueDate->format('M j'),
        };

        if ($status !== 'paid' && $bill->amount) {
            $totalUnpaid += (float) $bill->amount;
        }

        $lines[] = "  - {$bill->name}: {$amountStr}, {$statusLabel}" . ($bill->is_autopay ? ' [autopay]' : '');
    }

    $lines[] = "  Total upcoming unpaid: \$" . number_format($totalUnpaid, 2);

    return implode("\n", $lines);
}
```

- [ ] **Step 2: Register in `buildDynamicContext()`**

Add after the income tracking section:

```php
// Bills tracking
$sections[] = $this->buildBillsSection();
```

Add the import at the top of the file:

```php
use App\Models\Bill;
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add bills context to AI financial advisor"
```

---

### Task 5: Add Dashboard Bills Widget

**Files:**
- Create: `resources/views/livewire/dashboard/upcoming-bills.blade.php`
- Modify: `resources/views/pages/dashboard.blade.php`

- [ ] **Step 1: Create upcoming-bills widget**

A compact card showing the next 3-5 upcoming/overdue bills with status badges. Links to the bills page. Shows total upcoming amount.

```blade
<?php

use App\Models\Bill;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $now = now();
        $bills = Bill::where('is_active', true)->orderBy('due_day')->get();

        $billsWithStatus = $bills->map(function ($bill) use ($now) {
            $bill->status = $bill->statusForMonth($now);
            $bill->payment = $bill->matchingTransaction(
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            );
            return $bill;
        })->filter(fn ($b) => $b->status !== 'paid')
          ->sortBy('due_day')
          ->take(5);

        $totalUpcoming = $billsWithStatus->sum(fn ($b) => (float) ($b->amount ?? 0));

        return compact('billsWithStatus', 'totalUpcoming');
    }
};
?>

<flux:card>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon.calendar-days variant="mini" class="text-zinc-400 dark:text-zinc-500" />
            <flux:heading size="sm">Upcoming Bills</flux:heading>
        </div>
        <flux:link href="/bills" wire:navigate size="sm">View all</flux:link>
    </div>

    @if ($billsWithStatus->isEmpty())
        <div class="text-center py-4">
            <flux:text size="sm">All bills paid this month</flux:text>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($billsWithStatus as $bill)
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <flux:text size="sm" class="font-medium truncate">{{ $bill->name }}</flux:text>
                        <flux:text size="xs">Due {{ $bill->dueDateForMonth(now())->format('M j') }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:text size="sm" class="font-semibold">
                            {{ $bill->is_fixed ? '' : '~' }}${{ number_format($bill->amount ?? 0, 2) }}
                        </flux:text>
                        @if ($bill->status === 'overdue')
                            <flux:badge color="red" size="sm">Overdue</flux:badge>
                        @elseif ($bill->status === 'due_soon')
                            <flux:badge color="amber" size="sm">Due Soon</flux:badge>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totalUpcoming > 0)
            <flux:separator class="my-3" />
            <div class="flex items-center justify-between">
                <flux:text size="sm">Total upcoming</flux:text>
                <flux:text size="sm" class="font-semibold">${{ number_format($totalUpcoming, 2) }}</flux:text>
            </div>
        @endif
    @endif
</flux:card>
```

- [ ] **Step 2: Add to dashboard page**

In `resources/views/pages/dashboard.blade.php`, add after `flagged-transactions`:

```blade
<livewire:dashboard.upcoming-bills />
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add upcoming bills widget to dashboard"
```
