# Phase 4: Growth & Goals — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add financial goal tracking with progress visualization, integrate goals into Ollama's advisory context, build a household financial profile page with annual projections, and enhance the AI's long-term memory by referencing prior summaries in its analysis.

**Architecture:** New Goal model with migration, factory, and seeder. Goals integrate into the existing FinancialContextBuilder so all AI jobs and chat automatically reference them. A new Goals page with CRUD and progress visualization. A Financial Profile page consolidates income, expenses, goals, and net trajectory into one view. Summaries are enhanced to reference prior summaries for trend continuity.

**Tech Stack:** Livewire 4 SFC, ApexCharts, Tailwind CSS (zinc palette), existing OllamaService + FinancialContextBuilder

**Spec:** `docs/superpowers/specs/2026-04-30-steward-ai-design.md` — Phase 4 deliverables (lines 452-464)

---

## File Structure

```
steward/
├── app/Models/
│   └── Goal.php                                  # Create — goal model
├── database/
│   ├── migrations/
│   │   └── xxxx_create_goals_table.php           # Create
│   └── factories/
│       └── GoalFactory.php                       # Create
├── app/Services/
│   └── FinancialContextBuilder.php               # Modify — add goals + prior summaries to context
├── resources/views/
│   ├── livewire/
│   │   ├── goals/
│   │   │   └── goal-manager.blade.php            # Create — CRUD + progress visualization
│   │   └── profile/
│   │       └── financial-profile.blade.php       # Create — consolidated household view
│   └── pages/
│       ├── goals.blade.php                       # Create — new page
│       └── profile.blade.php                     # Create — new page
├── routes/
│   └── web.php                                   # Modify — add goal + profile routes
├── resources/views/livewire/layout/
│   └── sidebar.blade.php                         # Modify — add Goals + Profile nav items
└── tests/
    ├── Unit/
    │   └── Models/
    │       └── GoalTest.php                      # Create
    └── Feature/
        └── Livewire/
            ├── GoalManagerTest.php               # Create
            └── FinancialProfileTest.php          # Create
```

---

### Task 1: Goal Model, Migration, and Factory

**Files:**
- Create: `steward/database/migrations/xxxx_create_goals_table.php`
- Create: `steward/app/Models/Goal.php`
- Create: `steward/database/factories/GoalFactory.php`
- Test: `steward/tests/Unit/Models/GoalTest.php`

- [ ] **Step 1: Generate migration**

```bash
cd /Users/ahp-jamie/Documents/FinanceContainer/steward
php artisan make:migration create_goals_table
```

- [ ] **Step 2: Write migration**

```php
public function up(): void
{
    Schema::create('goals', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('target_amount', 10, 2);
        $table->decimal('current_amount', 10, 2)->default(0);
        $table->date('target_date')->nullable();
        $table->string('priority')->default('medium');
        $table->string('bucket')->nullable();
        $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
        $table->text('notes')->nullable();
        $table->boolean('is_completed')->default(false);
        $table->timestamps();
    });
}
```

- [ ] **Step 3: Write failing model tests**

```php
<?php
// steward/tests/Unit/Models/GoalTest.php
namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_progress_percentage(): void
    {
        $goal = Goal::factory()->create(['target_amount' => 10000, 'current_amount' => 2500]);

        $this->assertEquals(25.0, $goal->progressPercent());
    }

    public function test_computes_remaining_amount(): void
    {
        $goal = Goal::factory()->create(['target_amount' => 10000, 'current_amount' => 2500]);

        $this->assertEquals(7500.0, $goal->remaining());
    }

    public function test_computes_monthly_savings_needed(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 12000,
            'current_amount' => 0,
            'target_date' => now()->addMonths(12),
        ]);

        $this->assertEquals(1000.0, $goal->monthlySavingsNeeded());
    }

    public function test_monthly_savings_returns_null_without_target_date(): void
    {
        $goal = Goal::factory()->create(['target_date' => null]);

        $this->assertNull($goal->monthlySavingsNeeded());
    }

    public function test_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $goal = Goal::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($goal->category->is($category));
    }

    public function test_category_is_nullable(): void
    {
        $goal = Goal::factory()->create(['category_id' => null]);

        $this->assertNull($goal->category);
    }
}
```

- [ ] **Step 4: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="GoalTest"
```

- [ ] **Step 5: Create Goal model**

```php
<?php
// steward/app/Models/Goal.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'target_amount',
        'current_amount',
        'target_date',
        'priority',
        'bucket',
        'category_id',
        'notes',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'target_date' => 'date',
            'is_completed' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function progressPercent(): float
    {
        if ((float) $this->target_amount === 0.0) {
            return 0;
        }

        return round(((float) $this->current_amount / (float) $this->target_amount) * 100, 1);
    }

    public function remaining(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    public function monthlySavingsNeeded(): ?float
    {
        if (! $this->target_date) {
            return null;
        }

        $monthsLeft = max(1, now()->diffInMonths($this->target_date, false));

        if ($monthsLeft <= 0) {
            return $this->remaining();
        }

        return round($this->remaining() / $monthsLeft, 2);
    }
}
```

- [ ] **Step 6: Create GoalFactory**

```php
<?php
// steward/database/factories/GoalFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Emergency Fund', 'New Car', 'Roof Repair', 'Family Vacation', 'Kitchen Renovation']),
            'target_amount' => $this->faker->randomFloat(2, 1000, 30000),
            'current_amount' => $this->faker->randomFloat(2, 0, 5000),
            'target_date' => $this->faker->dateTimeBetween('+3 months', '+3 years'),
            'priority' => $this->faker->randomElement(['high', 'medium', 'low']),
            'bucket' => null,
            'category_id' => null,
            'notes' => null,
            'is_completed' => false,
        ];
    }
}
```

- [ ] **Step 7: Run migration and tests**

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test --filter="GoalTest"
```

Expected: All 6 tests PASS.

- [ ] **Step 8: Commit**

```bash
cd /Users/ahp-jamie/Documents/FinanceContainer
git add steward/database/migrations/ steward/app/Models/Goal.php steward/database/factories/GoalFactory.php steward/tests/Unit/Models/GoalTest.php
git commit -m "feat: add Goal model with progress tracking, monthly savings computation, and factory"
```

---

### Task 2: Goals Page — CRUD + Progress Visualization

**Files:**
- Create: `steward/resources/views/livewire/goals/goal-manager.blade.php`
- Create: `steward/resources/views/pages/goals.blade.php`
- Modify: `steward/routes/web.php` — add goals route
- Modify: `steward/resources/views/livewire/layout/sidebar.blade.php` — add Goals nav item
- Test: `steward/tests/Feature/Livewire/GoalManagerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/GoalManagerTest.php
namespace Tests\Feature\Livewire;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_goals_with_progress(): void
    {
        $user = User::factory()->create();
        Goal::factory()->create(['name' => 'New Car', 'target_amount' => 20000, 'current_amount' => 5000]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->assertSee('New Car')
            ->assertSee('20,000.00')
            ->assertSee('5,000.00')
            ->assertSee('25%');
    }

    public function test_can_create_goal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->set('name', 'Kitchen Renovation')
            ->set('targetAmount', '15000')
            ->set('targetDate', '2027-06-01')
            ->set('priority', 'high')
            ->call('save');

        $this->assertDatabaseHas('goals', [
            'name' => 'Kitchen Renovation',
            'target_amount' => 15000,
            'priority' => 'high',
        ]);
    }

    public function test_can_update_goal_progress(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['name' => 'Emergency Fund', 'current_amount' => 1000]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->call('edit', $goal->id)
            ->set('currentAmount', '2500')
            ->call('save');

        $goal->refresh();
        $this->assertEquals('2500.00', $goal->current_amount);
    }

    public function test_can_mark_goal_complete(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['is_completed' => false]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->call('toggleComplete', $goal->id);

        $goal->refresh();
        $this->assertTrue($goal->is_completed);
    }

    public function test_can_delete_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create();

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->call('delete', $goal->id);

        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_shows_monthly_savings_needed(): void
    {
        $user = User::factory()->create();
        Goal::factory()->create([
            'name' => 'Car Fund',
            'target_amount' => 12000,
            'current_amount' => 0,
            'target_date' => now()->addMonths(12),
        ]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->assertSee('1,000.00');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement goal-manager SFC**

Create `steward/resources/views/livewire/goals/goal-manager.blade.php`:

PHP class:
- Properties: `$name = ''`, `$targetAmount = ''`, `$currentAmount = '0'`, `$targetDate = ''`, `$priority = 'medium'`, `$notes = ''`, `$editingId = null`, `$showCompleted = false`
- `save()` — validate name required, targetAmount numeric > 0. Create or update Goal. Reset properties.
- `edit(int $id)` — populate properties from Goal
- `delete(int $id)` — delete the goal
- `toggleComplete(int $id)` — toggle is_completed
- `cancelEdit()` — reset properties
- `with()` returns: `activeGoals` (where is_completed=false, ordered by priority then target_date), `completedGoals` (where is_completed=true), `totalTargeted` (sum of active target_amounts), `totalSaved` (sum of active current_amounts)

Template:
- Summary bar: total goals count, total targeted, total saved, overall progress percentage
- List of active goals as cards, each showing:
  - Name, priority badge (high=red, medium=amber, low=green)
  - Progress bar (percentage filled, colored by progress: green >75%, amber 25-75%, red <25%)
  - Target amount, current amount, remaining
  - Monthly savings needed (if target_date set) with target date
  - Notes (if present, collapsed by default)
  - Edit, complete (lucide-check), delete buttons
- Completed goals section (collapsible, shown when showCompleted is true)
- Add/edit form: name, target amount, current amount, target date (optional), priority select, notes textarea

- [ ] **Step 4: Create goals page SFC**

Create `steward/resources/views/pages/goals.blade.php`:

```blade
<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Goals')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Goals</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Track your financial goals and savings targets.</p>
    </div>

    <livewire:goals.goal-manager />
</div>
```

- [ ] **Step 5: Add route and sidebar nav item**

Add to `routes/web.php` inside the auth middleware group:
```php
Route::livewire('/goals', 'pages::goals')->name('goals');
```

Update the sidebar nav items array to include Goals (with `lucide-target` icon) between Summaries and Chat.

- [ ] **Step 6: Run tests to verify they pass**

- [ ] **Step 7: Commit**

```bash
git add steward/resources/views/livewire/goals/ steward/resources/views/pages/goals.blade.php steward/routes/web.php steward/resources/views/livewire/layout/sidebar.blade.php steward/tests/Feature/Livewire/GoalManagerTest.php
git commit -m "feat: add Goals page with CRUD, progress visualization, and monthly savings tracking"
```

---

### Task 3: Integrate Goals into Ollama Context

**Files:**
- Modify: `steward/app/Services/FinancialContextBuilder.php`
- Test: `steward/tests/Unit/Services/FinancialContextBuilderTest.php` (add tests)

- [ ] **Step 1: Write failing tests**

Add to the existing `FinancialContextBuilderTest.php`:

```php
public function test_system_prompt_includes_active_goals(): void
{
    \App\Models\Goal::factory()->create([
        'name' => 'New Car',
        'target_amount' => 20000,
        'current_amount' => 5000,
        'target_date' => now()->addMonths(18),
        'priority' => 'high',
    ]);

    $prompt = $this->builder->buildSystemPrompt();

    $this->assertStringContainsString('New Car', $prompt);
    $this->assertStringContainsString('20,000.00', $prompt);
    $this->assertStringContainsString('5,000.00', $prompt);
    $this->assertStringContainsString('high', $prompt);
}

public function test_dynamic_context_includes_prior_summary_highlights(): void
{
    \App\Models\Summary::create([
        'type' => 'daily',
        'period_start' => now()->subDay()->startOfDay(),
        'period_end' => now()->subDay()->endOfDay(),
        'total_spent' => 87.50,
        'needs_spent' => 50, 'wants_spent' => 37.50, 'savings_spent' => 0,
        'total_income' => 6200,
        'ai_analysis' => 'Yesterday was a moderate spending day.',
    ]);

    $context = $this->builder->buildDynamicContext();

    $this->assertStringContainsString('moderate spending day', $context);
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Add goals to system prompt**

In `FinancialContextBuilder.php`, add a new private method `buildGoalsSection()`:

```php
private function buildGoalsSection(): string
{
    $goals = \App\Models\Goal::where('is_completed', false)
        ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END")
        ->get();

    if ($goals->isEmpty()) {
        return 'FINANCIAL GOALS: None set yet.';
    }

    $lines = $goals->map(function ($goal) {
        $progress = $goal->progressPercent();
        $remaining = $this->fmt($goal->remaining());
        $monthly = $goal->monthlySavingsNeeded();
        $monthlyStr = $monthly !== null ? " (need \${$this->fmt($monthly)}/mo)" : '';
        $dateStr = $goal->target_date ? " by {$goal->target_date->format('M Y')}" : '';

        return "- {$goal->name} [{$goal->priority}]: \${$this->fmt($goal->current_amount)} / \${$this->fmt($goal->target_amount)} ({$progress}% done, \${$remaining} remaining{$dateStr}{$monthlyStr})";
    });

    return "FINANCIAL GOALS:\n" . $lines->implode("\n");
}
```

Call `$this->buildGoalsSection()` in `buildSystemPrompt()` after the categories section.

- [ ] **Step 4: Add prior summaries to dynamic context**

Add a private method `buildPriorSummaries()`:

```php
private function buildPriorSummaries(): string
{
    $recent = \App\Models\Summary::where('type', 'daily')
        ->orderByDesc('period_start')
        ->limit(3)
        ->get();

    if ($recent->isEmpty()) {
        return '';
    }

    $lines = $recent->map(fn ($s) => "- {$s->period_start->format('M j')}: \${$this->fmt($s->total_spent)} spent — {$s->ai_analysis}");

    return "RECENT SUMMARIES:\n" . $lines->implode("\n");
}
```

Call it in `buildDynamicContext()` after the flagged transactions section.

- [ ] **Step 5: Run tests to verify they pass**

- [ ] **Step 6: Commit**

```bash
git add steward/app/Services/FinancialContextBuilder.php steward/tests/Unit/Services/FinancialContextBuilderTest.php
git commit -m "feat: integrate goals and prior summaries into Ollama financial context"
```

---

### Task 4: Financial Profile Page

**Files:**
- Create: `steward/resources/views/livewire/profile/financial-profile.blade.php`
- Create: `steward/resources/views/pages/profile.blade.php`
- Modify: `steward/routes/web.php` — add profile route
- Modify: `steward/resources/views/livewire/layout/sidebar.blade.php` — add Profile nav item
- Test: `steward/tests/Feature/Livewire/FinancialProfileTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/FinancialProfileTest.php
namespace Tests\Feature\Livewire;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Goal;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_income_summary(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['name' => 'Main Job', 'amount' => 2400, 'frequency' => 'biweekly']);
        IncomeSource::factory()->create(['name' => 'Church', 'amount' => 700, 'frequency' => 'biweekly']);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('Main Job')
            ->assertSee('Church')
            ->assertSee('6,716.67'); // total monthly
    }

    public function test_displays_net_monthly_position(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['amount' => 5000, 'frequency' => 'monthly']);

        $account = Account::factory()->create();
        $category = Category::factory()->create(['default_bucket' => 'needs']);
        Budget::factory()->create(['category_id' => $category->id, 'month' => now()->format('Y-m'), 'budgeted_amount' => 3000, 'bucket' => 'needs']);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('5,000.00'); // income
    }

    public function test_displays_goals_overview(): void
    {
        $user = User::factory()->create();
        Goal::factory()->create(['name' => 'New Car', 'target_amount' => 20000, 'current_amount' => 5000]);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('New Car')
            ->assertSee('25%');
    }

    public function test_displays_annual_projection(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['amount' => 5000, 'frequency' => 'monthly']);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('60,000.00'); // annual income
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement financial-profile SFC**

Create `steward/resources/views/livewire/profile/financial-profile.blade.php`:

PHP class `with()` returns:
- `incomeSources` — active IncomeSource records
- `totalMonthlyIncome` — sum of monthlyAmount()
- `annualIncome` — totalMonthlyIncome * 12
- `accounts` — all Account records with balances
- `totalBalance` — sum of all current_balance
- `currentMonthSpending` — sum of Transaction amounts this month
- `budgetRatios` — from AppSetting
- `currentMonthByBucket` — spending per bucket this month
- `activeGoals` — Goal where is_completed=false
- `totalGoalTarget` — sum of active target_amounts
- `totalGoalSaved` — sum of active current_amounts
- `annualProjection` — estimated year-end savings based on (monthly income - average monthly spending) * months remaining

Template layout — four sections in a grid:

**Section 1: Income & Accounts** (card)
- List of income sources with amounts and frequencies
- Total monthly income highlighted
- Account balances with total

**Section 2: Budget Overview** (card)
- 50/30/20 targets vs actual this month
- Monthly surplus/deficit
- Annual income projection

**Section 3: Goals Progress** (card)
- List of active goals with progress bars
- Total targeted vs total saved
- Overall goal progress percentage

**Section 4: Annual Outlook** (card with ApexCharts)
- Projected savings by year-end based on current trajectory
- Monthly income vs average spending as a comparison bar chart
- "At this rate, you'll save $X this year" summary text

- [ ] **Step 4: Create profile page and add route + nav**

Create `steward/resources/views/pages/profile.blade.php` — standard page SFC wrapping the component.

Add to `routes/web.php`:
```php
Route::livewire('/profile', 'pages::profile')->name('profile');
```

Add "Profile" (lucide-user icon) to the sidebar nav items before Settings.

- [ ] **Step 5: Run tests to verify they pass**

- [ ] **Step 6: Commit**

```bash
git add steward/resources/views/livewire/profile/ steward/resources/views/pages/profile.blade.php steward/routes/web.php steward/resources/views/livewire/layout/sidebar.blade.php steward/tests/Feature/Livewire/FinancialProfileTest.php
git commit -m "feat: add Financial Profile page with income summary, goals overview, and annual projections"
```

---

### Task 5: Dashboard Goals Widget

**Files:**
- Create: `steward/resources/views/livewire/dashboard/goals-summary.blade.php`
- Modify: `steward/resources/views/pages/dashboard.blade.php`

- [ ] **Step 1: Create goals-summary SFC**

A compact dashboard widget showing active goals at a glance.

PHP class `with()`:
- `goals` — Goal where is_completed=false, ordered by priority, limit 3
- `totalProgress` — overall (sum current / sum target) * 100

Template:
- Card with "Goals" header + link to /goals page
- Up to 3 goals, each as a compact row: name, progress bar, percentage, remaining amount
- If more than 3 goals, "View all X goals" link
- If no goals: "No goals set. Set your first goal." with link

- [ ] **Step 2: Add to dashboard page**

In `steward/resources/views/pages/dashboard.blade.php`, add `<livewire:dashboard.goals-summary />` after the flagged transactions widget.

- [ ] **Step 3: Commit**

```bash
git add steward/resources/views/livewire/dashboard/goals-summary.blade.php steward/resources/views/pages/dashboard.blade.php
git commit -m "feat: add goals summary widget to dashboard"
```

---

### Task 6: Run Full Test Suite and Visual Verification

- [ ] **Step 1: Run all tests**

```bash
docker compose exec app php artisan test
```

Expected: All tests pass.

- [ ] **Step 2: Build frontend**

```bash
cd steward && npm run build
```

- [ ] **Step 3: Visual verification**

Open each new/modified page:
1. Goals — CRUD, progress bars, monthly savings, priority badges
2. Profile — income, accounts, budget overview, goals, annual outlook
3. Dashboard — new goals widget at bottom
4. Sidebar — Goals and Profile nav items present
5. Chat — ask Ollama about goals, verify it references them in context

- [ ] **Step 4: Commit any fixes**
