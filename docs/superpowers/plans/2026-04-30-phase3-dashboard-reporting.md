# Phase 3: Dashboard & Reporting — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full frontend experience — dashboard home page with data visualization, budget management, category CRUD, summaries archive, and enhancements to existing pages (balance history charts, bulk categorization, settings expansion).

**Architecture:** All pages are Livewire 4 SFC page components using `Route::livewire()` and `wire:navigate`. Data visualization uses ApexCharts (already installed, exposed as `window.ApexCharts`). Components follow the established zinc color palette with light/dark mode, Lucide icons, and iOS 26-style bezel borders. No new models or services — this phase is purely frontend, consuming existing data from Phases 1 and 2.

**Tech Stack:** Livewire 4 SFC, ApexCharts (vanilla JS via `window.ApexCharts`), Tailwind CSS (zinc palette), Lucide icons (`mallardduck/blade-lucide-icons`)

**Spec:** `docs/superpowers/specs/2026-04-30-steward-ai-design.md` — Frontend section (lines 302-391) and Phase 3 deliverables (lines 436-451)

**Design rules:**
- NO EMOJIS. Lucide icons only.
- Zinc color palette with `dark:` variants on everything.
- All Livewire components use SFC format (anonymous class + Blade in one `.blade.php` file).
- Page components live in `resources/views/pages/` and use `#[Layout('components.layouts.app')]` + `#[Title('...')]`.
- Embedded components live in `resources/views/livewire/`.
- ApexCharts: render via Alpine `x-data` + `x-init` calling `new ApexCharts(el, options).render()`. Pass data from Livewire via `@js()` directive.

---

## File Structure

```
steward/resources/views/
├── livewire/
│   ├── dashboard/
│   │   ├── balance-cards.blade.php        # Create — account balances + next payday
│   │   ├── budget-progress.blade.php      # Create — 50-30-20 bar with warnings
│   │   ├── spending-chart.blade.php       # Create — 7/30 day ApexCharts trend
│   │   ├── summary-snippet.blade.php      # Create — latest daily summary
│   │   └── flagged-transactions.blade.php # Create — needs-review list with inline categorize
│   ├── budgets/
│   │   └── budget-manager.blade.php       # Create — budget CRUD with % of income, progress bars
│   ├── categories/
│   │   └── category-manager.blade.php     # Create — category CRUD with icon picker, avg spend
│   ├── summaries/
│   │   └── summary-archive.blade.php      # Create — tabbed daily/weekly/monthly archive
│   ├── accounts/
│   │   └── account-list.blade.php         # Modify — add balance history chart
│   ├── transactions/
│   │   └── transaction-list.blade.php     # Modify — add bulk categorization
│   └── settings/
│       ├── budget-ratios.blade.php        # Create — 50/30/20 ratio config
│       └── email-recipients.blade.php     # Create — email list management
├── pages/
│   ├── dashboard.blade.php                # Modify — replace placeholder
│   ├── budgets.blade.php                  # Modify — replace placeholder
│   ├── categories.blade.php               # Modify — replace placeholder
│   ├── summaries.blade.php                # Modify — replace placeholder
│   └── settings.blade.php                 # Modify — add new settings components
└── tests/Feature/Livewire/
    ├── Dashboard/
    │   ├── BalanceCardsTest.php            # Create
    │   ├── BudgetProgressTest.php         # Create
    │   └── SpendingChartTest.php          # Create
    ├── BudgetManagerTest.php              # Create
    ├── CategoryManagerTest.php            # Create
    └── SummaryArchiveTest.php             # Create
```

---

### Task 1: Dashboard — Balance Cards

**Files:**
- Create: `steward/resources/views/livewire/dashboard/balance-cards.blade.php`
- Test: `steward/tests/Feature/Livewire/Dashboard/BalanceCardsTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/Dashboard/BalanceCardsTest.php
namespace Tests\Feature\Livewire\Dashboard;

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BalanceCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_account_balances(): void
    {
        $user = User::factory()->create();
        Account::factory()->create(['name' => 'Checking', 'current_balance' => 1247.33, 'type' => 'checking']);
        Account::factory()->create(['name' => 'Savings', 'current_balance' => 3892.10, 'type' => 'savings']);

        Livewire::actingAs($user)
            ->test('dashboard.balance-cards')
            ->assertSee('Checking')
            ->assertSee('1,247.33')
            ->assertSee('Savings')
            ->assertSee('3,892.10');
    }

    public function test_displays_next_payday(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create([
            'name' => 'Main Job',
            'next_pay_date' => now()->addDays(3),
        ]);

        Livewire::actingAs($user)
            ->test('dashboard.balance-cards')
            ->assertSee('3 days');
    }

    public function test_shows_empty_state_when_no_accounts(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('dashboard.balance-cards')
            ->assertSee('No accounts connected');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="BalanceCardsTest"
```

- [ ] **Step 3: Implement the component**

Create `steward/resources/views/livewire/dashboard/balance-cards.blade.php` as a Livewire 4 SFC:

PHP class:
- `with()` returns: `accounts` (all Account records), `nextPayday` (soonest `next_pay_date` from active IncomeSource, as Carbon), `daysUntilPay` (integer days until next payday)

Template:
- Grid of cards (`grid grid-cols-1 md:grid-cols-3 gap-4`)
- Each account: card with icon (lucide-wallet for checking, lucide-piggy-bank for savings), name, formatted balance, "available" sub-line
- Next payday card: icon (lucide-calendar), "Next Payday" label, "X days" large text, date below
- Empty state if no accounts: "No accounts connected" with link to /accounts
- All cards use `rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5`

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Commit**

```bash
git add steward/resources/views/livewire/dashboard/balance-cards.blade.php steward/tests/Feature/Livewire/Dashboard/BalanceCardsTest.php
git commit -m "feat: add dashboard balance cards with next payday countdown"
```

---

### Task 2: Dashboard — 50-30-20 Budget Progress Bar

**Files:**
- Create: `steward/resources/views/livewire/dashboard/budget-progress.blade.php`
- Test: `steward/tests/Feature/Livewire/Dashboard/BudgetProgressTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/Dashboard/BudgetProgressTest.php
namespace Tests\Feature\Livewire\Dashboard;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_bucket_percentages(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);

        $account = Account::factory()->create();
        Transaction::factory()->create(['account_id' => $account->id, 'amount' => 500, 'budget_bucket' => 'needs', 'date' => now()]);
        Transaction::factory()->create(['account_id' => $account->id, 'amount' => 300, 'budget_bucket' => 'wants', 'date' => now()]);
        Transaction::factory()->create(['account_id' => $account->id, 'amount' => 100, 'budget_bucket' => 'savings', 'date' => now()]);

        Livewire::actingAs($user)
            ->test('dashboard.budget-progress')
            ->assertSee('Needs')
            ->assertSee('Wants')
            ->assertSee('Savings');
    }

    public function test_shows_warning_when_over_target(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);
        IncomeSource::factory()->create(['amount' => 1000, 'frequency' => 'monthly']);

        $account = Account::factory()->create();
        // Wants at 60% — well over the 30% target
        Transaction::factory()->create(['account_id' => $account->id, 'amount' => 600, 'budget_bucket' => 'wants', 'date' => now()]);

        Livewire::actingAs($user)
            ->test('dashboard.budget-progress')
            ->assertSee('over');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement the component**

Create `steward/resources/views/livewire/dashboard/budget-progress.blade.php` as SFC:

PHP class:
- `with()` computes: total monthly income from active IncomeSource records, current month's spending per bucket (needs/wants/savings) from Transaction, target ratios from AppSetting, actual percentages, and whether each bucket is over target

Template:
- Section header: current month name + "50 / 30 / 20 Breakdown"
- Stacked horizontal bar showing actual percentages with colored segments (blue for needs, orange for wants, green for savings)
- Below the bar: three columns showing each bucket with target %, actual %, dollar amount spent, and a warning badge if over target
- Use `rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5` for the container

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Commit**

```bash
git add steward/resources/views/livewire/dashboard/budget-progress.blade.php steward/tests/Feature/Livewire/Dashboard/BudgetProgressTest.php
git commit -m "feat: add dashboard 50-30-20 budget progress bar with over-target warnings"
```

---

### Task 3: Dashboard — Spending Trend Chart

**Files:**
- Create: `steward/resources/views/livewire/dashboard/spending-chart.blade.php`
- Test: `steward/tests/Feature/Livewire/Dashboard/SpendingChartTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/Dashboard/SpendingChartTest.php
namespace Tests\Feature\Livewire\Dashboard;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpendingChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_provides_chart_data_for_last_7_days(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();

        Transaction::factory()->create(['account_id' => $account->id, 'amount' => 50, 'date' => now()]);
        Transaction::factory()->create(['account_id' => $account->id, 'amount' => 30, 'date' => now()->subDay()]);

        $component = Livewire::actingAs($user)->test('dashboard.spending-chart');

        $component->assertSet('days', 7);
        $this->assertNotEmpty($component->get('chartData'));
    }

    public function test_can_toggle_to_30_days(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('dashboard.spending-chart')
            ->call('setDays', 30)
            ->assertSet('days', 30);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement the component**

Create `steward/resources/views/livewire/dashboard/spending-chart.blade.php` as SFC:

PHP class:
- Property: `$days = 7`
- Method: `setDays(int $days)` — sets the days property (7 or 30)
- `with()` computes `chartData`: an array of `['date' => 'M j', 'amount' => float]` for each day in the range, summing transaction amounts per day. Also computes `chartLabels` and `chartValues` as separate arrays for ApexCharts.

Template:
- Header with "Spending Trend" title and toggle buttons for 7/30 days (pill-style, active state highlighted)
- ApexCharts area/bar chart rendered via Alpine: `x-data` + `x-init` calling `new ApexCharts($refs.chart, {...}).render()`. Pass data using `@js($chartLabels)` and `@js($chartValues)`.
- Chart options: bar type, zinc-400 gridlines, blue-500 bar color, responsive, dark mode aware (use CSS variable or hardcode both palettes in Alpine with a theme check)
- Container: `rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5`

Important: When `$days` changes, the chart needs to re-render. Use `wire:key="chart-{{ $days }}"` on the chart container to force Alpine to reinitialize.

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Commit**

```bash
git add steward/resources/views/livewire/dashboard/spending-chart.blade.php steward/tests/Feature/Livewire/Dashboard/SpendingChartTest.php
git commit -m "feat: add dashboard spending trend chart with 7/30 day toggle"
```

---

### Task 4: Dashboard — Summary Snippet + Flagged Transactions

**Files:**
- Create: `steward/resources/views/livewire/dashboard/summary-snippet.blade.php`
- Create: `steward/resources/views/livewire/dashboard/flagged-transactions.blade.php`

- [ ] **Step 1: Create summary snippet SFC**

PHP class:
- `with()` returns: `latestSummary` — the most recent daily Summary model, or null

Template:
- If summary exists: card showing "Today's Summary" header, period date, `ai_analysis` text (truncated to ~200 chars with "Read more" link to /summaries), total spent
- If no summary: "No summaries yet. They'll appear after your first daily sync."
- Card styling matches other dashboard cards

- [ ] **Step 2: Create flagged transactions SFC**

PHP class:
- `with()` returns: `flaggedTransactions` — Transaction where `needs_review = true`, limit 5, with category and account relationships
- Method: `assignCategory(int $transactionId, int $categoryId)` — same logic as TransactionList's assignCategory (set category_id, budget_bucket, needs_review=false, confidence=1.0)
- `with()` also returns `categories` for the dropdown

Template:
- If flagged transactions exist: card with "Needs Review" header + count badge, list of transactions with merchant, amount, date, and inline category dropdown
- If none: card with "All Clear" message and check icon
- Warning styling: amber accents for the header/badge

- [ ] **Step 3: Commit**

```bash
git add steward/resources/views/livewire/dashboard/summary-snippet.blade.php steward/resources/views/livewire/dashboard/flagged-transactions.blade.php
git commit -m "feat: add dashboard summary snippet and flagged transactions widget"
```

---

### Task 5: Dashboard Page Assembly

**Files:**
- Modify: `steward/resources/views/pages/dashboard.blade.php`

- [ ] **Step 1: Replace the placeholder dashboard page**

Update `steward/resources/views/pages/dashboard.blade.php` to compose all the dashboard widgets:

```blade
<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Your financial overview.</p>
    </div>

    <div class="space-y-6">
        {{-- Row 1: Balance cards --}}
        <livewire:dashboard.balance-cards />

        {{-- Row 2: Budget progress --}}
        <livewire:dashboard.budget-progress />

        {{-- Row 3: Chart + Summary side by side --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <livewire:dashboard.spending-chart />
            <livewire:dashboard.summary-snippet />
        </div>

        {{-- Row 4: Flagged transactions --}}
        <livewire:dashboard.flagged-transactions />
    </div>
</div>
```

- [ ] **Step 2: Verify in browser**

Open `http://localhost:8080/dashboard`, verify all widgets render.

- [ ] **Step 3: Commit**

```bash
git add steward/resources/views/pages/dashboard.blade.php
git commit -m "feat: assemble dashboard page with all widgets"
```

---

### Task 6: Budgets Page

**Files:**
- Create: `steward/resources/views/livewire/budgets/budget-manager.blade.php`
- Modify: `steward/resources/views/pages/budgets.blade.php`
- Test: `steward/tests/Feature/Livewire/BudgetManagerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/BudgetManagerTest.php
namespace Tests\Feature\Livewire;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_budgets_with_spent_and_remaining(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['amount' => 5000, 'frequency' => 'monthly']);
        $category = Category::factory()->create(['name' => 'Groceries', 'default_bucket' => 'needs']);
        Budget::factory()->create(['category_id' => $category->id, 'month' => now()->format('Y-m'), 'budgeted_amount' => 400, 'bucket' => 'needs']);

        $account = Account::factory()->create();
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $category->id, 'amount' => 250, 'date' => now(), 'budget_bucket' => 'needs']);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->assertSee('Groceries')
            ->assertSee('400.00')
            ->assertSee('250.00');
    }

    public function test_shows_percentage_of_income(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['amount' => 5000, 'frequency' => 'monthly']);
        $category = Category::factory()->create(['name' => 'Rent', 'default_bucket' => 'needs']);
        Budget::factory()->create(['category_id' => $category->id, 'month' => now()->format('Y-m'), 'budgeted_amount' => 1500, 'bucket' => 'needs']);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->assertSee('30%'); // 1500 / 5000 = 30%
    }

    public function test_can_create_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Dining', 'default_bucket' => 'wants']);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->set('editCategoryId', $category->id)
            ->set('editAmount', '300')
            ->call('saveBudget');

        $this->assertDatabaseHas('budgets', [
            'category_id' => $category->id,
            'budgeted_amount' => 300,
            'month' => now()->format('Y-m'),
        ]);
    }

    public function test_warns_when_total_exceeds_income(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['amount' => 1000, 'frequency' => 'monthly']);
        $cat1 = Category::factory()->create(['default_bucket' => 'needs']);
        $cat2 = Category::factory()->create(['default_bucket' => 'wants']);
        Budget::factory()->create(['category_id' => $cat1->id, 'month' => now()->format('Y-m'), 'budgeted_amount' => 800, 'bucket' => 'needs']);
        Budget::factory()->create(['category_id' => $cat2->id, 'month' => now()->format('Y-m'), 'budgeted_amount' => 400, 'bucket' => 'wants']);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->assertSee('over income');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement budget-manager SFC**

PHP class:
- Properties: `$editCategoryId = null`, `$editAmount = ''`
- Method: `saveBudget()` — validates, creates/updates Budget for the current month with the category's default_bucket
- Method: `deleteBudget(int $budgetId)` — deletes the budget
- Method: `editBudget(int $budgetId)` — populates edit properties
- `with()` returns: current month's budgets with category + spent amounts (via subquery or computed), total monthly income, total budgeted, whether over income, categories without budgets (for the "add" dropdown), bucket subtotals (needs/wants/savings totals with progress bar percentages)

Template layout:
- Header: month name, total budgeted vs total income, over-income warning (red banner if total > income)
- Three sections grouped by bucket (Needs / Wants / Savings), each with:
  - Bucket header with target % and actual total
  - Progress bar for the bucket
  - List of categories in that bucket: category icon + name, budgeted amount, % of income, spent this month, remaining, visual progress bar per category
  - Edit/delete inline actions
- Add budget form at bottom: category dropdown (only shows categories without a budget this month), amount input, save button

- [ ] **Step 4: Update budgets page**

Replace `steward/resources/views/pages/budgets.blade.php` placeholder with the budget-manager component.

- [ ] **Step 5: Run tests to verify they pass**

- [ ] **Step 6: Commit**

```bash
git add steward/resources/views/livewire/budgets/ steward/resources/views/pages/budgets.blade.php steward/tests/Feature/Livewire/BudgetManagerTest.php
git commit -m "feat: add Budgets page with CRUD, % of income, progress bars, over-income warning"
```

---

### Task 7: Categories Page

**Files:**
- Create: `steward/resources/views/livewire/categories/category-manager.blade.php`
- Modify: `steward/resources/views/pages/categories.blade.php`
- Test: `steward/tests/Feature/Livewire/CategoryManagerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/CategoryManagerTest.php
namespace Tests\Feature\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_categories_with_icons(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Groceries', 'icon' => 'shopping-cart', 'default_bucket' => 'needs', 'is_essential' => true]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->assertSee('Groceries')
            ->assertSee('needs');
    }

    public function test_can_create_category(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->set('name', 'Gym')
            ->set('icon', 'dumbbell')
            ->set('defaultBucket', 'wants')
            ->set('isEssential', false)
            ->call('save');

        $this->assertDatabaseHas('categories', ['name' => 'Gym', 'icon' => 'dumbbell', 'default_bucket' => 'wants', 'is_essential' => false]);
    }

    public function test_can_update_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Food', 'is_system' => false]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->call('edit', $category->id)
            ->set('name', 'Food & Drink')
            ->call('save');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Food & Drink']);
    }

    public function test_can_delete_non_system_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_system' => false]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->call('delete', $category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_system_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_system' => true]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->call('delete', $category->id);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_shows_average_spend(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Coffee']);
        $account = Account::factory()->create();

        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $category->id, 'amount' => 30, 'date' => now()->subMonth()]);
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $category->id, 'amount' => 60, 'date' => now()->subMonths(2)]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->assertSee('30.00'); // average of 90 over 3 months
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement category-manager SFC**

PHP class:
- Properties: `$name = ''`, `$icon = 'tag'`, `$defaultBucket = 'wants'`, `$isEssential = false`, `$editingId = null`, `$lookbackMonths = 3`
- Method: `save()` — create or update category
- Method: `edit(int $id)` — populate properties from existing category
- Method: `delete(int $id)` — only delete if `is_system === false`
- Method: `cancelEdit()` — reset properties
- `with()` returns: all categories with their `averageSpend($lookbackMonths)` and transaction count

Template:
- Table/grid of categories: icon (rendered via `x-dynamic-component`), name, bucket badge, essential badge, average monthly spend, trend indicator (up/down/stable based on comparing last month vs average)
- System categories can't be deleted (hide delete button)
- Add/edit form: name input, icon picker (dropdown or grid of common Lucide icon names), bucket select (needs/wants/savings), essential toggle
- Lookback period selector: 3 / 6 / 12 months buttons

For the icon picker: provide a curated list of ~30 common Lucide icons as a simple select dropdown with icon preview. Don't build a full icon search — that's overengineering.

- [ ] **Step 4: Update categories page**

Replace placeholder with category-manager component.

- [ ] **Step 5: Run tests to verify they pass**

- [ ] **Step 6: Commit**

```bash
git add steward/resources/views/livewire/categories/ steward/resources/views/pages/categories.blade.php steward/tests/Feature/Livewire/CategoryManagerTest.php
git commit -m "feat: add Categories page with CRUD, icon picker, average spend, trend indicators"
```

---

### Task 8: Summaries Page

**Files:**
- Create: `steward/resources/views/livewire/summaries/summary-archive.blade.php`
- Modify: `steward/resources/views/pages/summaries.blade.php`
- Test: `steward/tests/Feature/Livewire/SummaryArchiveTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/SummaryArchiveTest.php
namespace Tests\Feature\Livewire;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SummaryArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_daily_summaries(): void
    {
        $user = User::factory()->create();
        Summary::create([
            'type' => 'daily',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'total_spent' => 47.22,
            'needs_spent' => 38.12,
            'wants_spent' => 9.10,
            'savings_spent' => 0,
            'total_income' => 6200,
            'ai_analysis' => 'Light spending day.',
        ]);

        Livewire::actingAs($user)
            ->test('summaries.summary-archive')
            ->assertSee('47.22')
            ->assertSee('Light spending day.');
    }

    public function test_can_switch_tabs(): void
    {
        $user = User::factory()->create();
        Summary::create([
            'type' => 'weekly',
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'total_spent' => 523.00,
            'needs_spent' => 300, 'wants_spent' => 200, 'savings_spent' => 23,
            'total_income' => 6200,
            'ai_analysis' => 'Moderate week.',
        ]);

        Livewire::actingAs($user)
            ->test('summaries.summary-archive')
            ->call('setTab', 'weekly')
            ->assertSet('activeTab', 'weekly')
            ->assertSee('523.00');
    }

    public function test_shows_empty_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('summaries.summary-archive')
            ->assertSee('No summaries yet');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement summary-archive SFC**

PHP class:
- Property: `$activeTab = 'daily'`
- Method: `setTab(string $tab)` — sets activeTab to 'daily', 'weekly', or 'monthly'
- `with()` returns: summaries for the active tab type, ordered by period_start desc, paginated (10 per page)

Template:
- Tab bar: Daily / Weekly / Monthly pills, active state highlighted
- List of summaries: each as an expandable card showing:
  - Period dates, total spent
  - 50-30-20 mini breakdown (three colored bars or numbers)
  - AI analysis text
  - AI advice text (if present)
  - Habit flags (if present, rendered as badges)
- Empty state: "No summaries yet. They'll appear after your first daily sync."
- Pagination at bottom

- [ ] **Step 4: Update summaries page**

Replace placeholder with summary-archive component.

- [ ] **Step 5: Run tests to verify they pass**

- [ ] **Step 6: Commit**

```bash
git add steward/resources/views/livewire/summaries/ steward/resources/views/pages/summaries.blade.php steward/tests/Feature/Livewire/SummaryArchiveTest.php
git commit -m "feat: add Summaries page with daily/weekly/monthly tabs and archive"
```

---

### Task 9: Settings — Budget Ratios + Email Recipients

**Files:**
- Create: `steward/resources/views/livewire/settings/budget-ratios.blade.php`
- Create: `steward/resources/views/livewire/settings/email-recipients.blade.php`
- Modify: `steward/resources/views/pages/settings.blade.php`
- Test: `steward/tests/Feature/Livewire/BudgetRatiosTest.php`
- Test: `steward/tests/Feature/Livewire/EmailRecipientsTest.php`

- [ ] **Step 1: Write failing tests for BudgetRatios**

```php
<?php
// steward/tests/Feature/Livewire/BudgetRatiosTest.php
namespace Tests\Feature\Livewire;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetRatiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_current_ratios(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        Livewire::actingAs($user)
            ->test('settings.budget-ratios')
            ->assertSet('needs', 50)
            ->assertSet('wants', 30)
            ->assertSet('savings', 20);
    }

    public function test_can_update_ratios(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        Livewire::actingAs($user)
            ->test('settings.budget-ratios')
            ->set('needs', 60)
            ->set('wants', 25)
            ->set('savings', 15)
            ->call('save');

        $this->assertEquals(['needs' => 60, 'wants' => 25, 'savings' => 15], AppSetting::getValue('budget_ratios'));
    }

    public function test_validates_ratios_sum_to_100(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        Livewire::actingAs($user)
            ->test('settings.budget-ratios')
            ->set('needs', 60)
            ->set('wants', 30)
            ->set('savings', 20)
            ->call('save')
            ->assertHasErrors();
    }
}
```

- [ ] **Step 2: Write failing tests for EmailRecipients**

```php
<?php
// steward/tests/Feature/Livewire/EmailRecipientsTest.php
namespace Tests\Feature\Livewire;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_existing_recipients(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('email_recipients', ['jamie@test.com', 'wife@test.com']);

        Livewire::actingAs($user)
            ->test('settings.email-recipients')
            ->assertSee('jamie@test.com')
            ->assertSee('wife@test.com');
    }

    public function test_can_add_recipient(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('email_recipients', []);

        Livewire::actingAs($user)
            ->test('settings.email-recipients')
            ->set('newEmail', 'jamie@test.com')
            ->call('addRecipient');

        $this->assertContains('jamie@test.com', AppSetting::getValue('email_recipients'));
    }

    public function test_can_remove_recipient(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('email_recipients', ['jamie@test.com', 'wife@test.com']);

        Livewire::actingAs($user)
            ->test('settings.email-recipients')
            ->call('removeRecipient', 0);

        $this->assertNotContains('jamie@test.com', AppSetting::getValue('email_recipients'));
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

- [ ] **Step 4: Implement budget-ratios SFC**

PHP class:
- Properties: `$needs`, `$wants`, `$savings`, `$saved = false`
- `mount()` — load from AppSetting
- `save()` — validate sum equals 100, save to AppSetting

Template:
- Three number inputs for needs/wants/savings with labels
- Live sum display showing current total (green if 100, red if not)
- Save button

- [ ] **Step 5: Implement email-recipients SFC**

PHP class:
- Properties: `$newEmail = ''`
- `addRecipient()` — validate email format, add to AppSetting array
- `removeRecipient(int $index)` — remove by index from AppSetting array
- `with()` returns recipients array from AppSetting

Template:
- List of emails with remove button each
- Add form: email input + add button

- [ ] **Step 6: Update settings page**

Add both new components to the settings page alongside the existing ones.

- [ ] **Step 7: Run tests to verify they pass**

- [ ] **Step 8: Commit**

```bash
git add steward/resources/views/livewire/settings/ steward/resources/views/pages/settings.blade.php steward/tests/Feature/Livewire/BudgetRatiosTest.php steward/tests/Feature/Livewire/EmailRecipientsTest.php
git commit -m "feat: add budget ratio config and email recipients management to Settings"
```

---

### Task 10: Accounts Page — Balance History Chart

**Files:**
- Modify: `steward/resources/views/livewire/accounts/account-list.blade.php`

- [ ] **Step 1: Add balance history tracking**

The account balances are updated on each Plaid sync. For a balance history chart, we need historical data. Since we don't have a dedicated balance_history table, we'll use transaction data to show a cumulative spending chart per account for the current month. This is the simplest approach without adding new migrations.

Add to the account-list SFC's `with()` method: `chartData` — for each account, compute daily cumulative spending for the last 30 days.

- [ ] **Step 2: Add ApexCharts to the account cards**

In the template, below each account's balance display, add a small area chart (sparkline-style) rendered via Alpine + ApexCharts showing the daily spending pattern. Use `wire:key` to handle re-renders.

Chart options: sparkline (no axis labels, no grid), 100px height, blue stroke for checking, indigo for savings.

- [ ] **Step 3: Commit**

```bash
git add steward/resources/views/livewire/accounts/account-list.blade.php
git commit -m "feat: add sparkline spending charts to account cards"
```

---

### Task 11: Transactions Page — Bulk Categorization

**Files:**
- Modify: `steward/resources/views/livewire/transactions/transaction-list.blade.php`

- [ ] **Step 1: Add bulk selection and categorization**

Add to the SFC's PHP class:
- Property: `$selectedIds = []`
- Property: `$bulkCategoryId = null`
- Method: `toggleSelect(int $id)` — add/remove from selectedIds
- Method: `selectAll()` — select all visible transaction IDs
- Method: `deselectAll()` — clear selectedIds
- Method: `bulkCategorize()` — for each selected transaction, assign the category, set budget_bucket, needs_review=false, confidence=1.0. Then clear selection.

Add to template:
- Checkbox column in the table (header checkbox for select all)
- Bulk action bar that appears when `count($selectedIds) > 0`: shows count, category dropdown, "Categorize Selected" button, "Clear" button
- Sticky at top of table area

- [ ] **Step 2: Commit**

```bash
git add steward/resources/views/livewire/transactions/transaction-list.blade.php
git commit -m "feat: add bulk categorization to Transactions page"
```

---

### Task 12: Run Full Test Suite and Visual Verification

- [ ] **Step 1: Run all tests**

```bash
docker compose exec app php artisan test
```

Expected: All tests pass.

- [ ] **Step 2: Build frontend assets**

```bash
cd steward && npm run build
```

- [ ] **Step 3: Visual verification**

Open each page in the browser and verify:
1. Dashboard — balance cards, 50-30-20 bar, spending chart, summary snippet, flagged transactions
2. Budgets — budget list with progress bars, add/edit, over-income warning
3. Categories — category table with icons, CRUD, average spend
4. Summaries — tab switching, summary cards
5. Settings — all four sections (income, sync, budget ratios, email recipients)
6. Accounts — sparkline charts on account cards
7. Transactions — bulk selection and categorization

- [ ] **Step 4: Commit any fixes**

```bash
git status
# If fixes needed, stage and commit
```
