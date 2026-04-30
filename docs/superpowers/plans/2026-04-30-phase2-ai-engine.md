# Phase 2: AI Engine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate Ollama for transaction auto-categorization, financial health analysis, budget recommendations, payment optimization, summary generation, and an interactive advisory chat.

**Architecture:** OllamaService wraps the Ollama HTTP API, building system prompts from DB state and assembling dynamic financial context per job. Six queued jobs form a chained pipeline (sync → categorize → budget check → health check → payment optimizer → summary). A Livewire SFC chat component streams responses for interactive advisory conversations. All AI jobs run on a dedicated Redis `ai` queue.

**Tech Stack:** Ollama HTTP API, Laravel queued jobs, Redis queues, Livewire 4 SFC with streaming, Laravel Mail

**Spec:** `docs/superpowers/specs/2026-04-30-steward-ai-design.md` — Ollama Integration section (lines 241-299) and Phase 2 deliverables (lines 418-434)

---

## File Structure

```
steward/
├── app/
│   ├── Jobs/
│   │   ├── PlaidSyncJob.php              # Modify — chain CategorizationJob after sync
│   │   ├── CategorizationJob.php         # Create — auto-categorize new transactions via Ollama
│   │   ├── BudgetCheckJob.php            # Create — 50-30-20 analysis, per-category vs budget
│   │   ├── HealthCheckJob.php            # Create — habit detection, budget recommendations
│   │   ├── PaymentOptimizerJob.php       # Create — essential bill timing, payment calendar
│   │   └── SummaryJob.php               # Create — compile daily/weekly/monthly summaries
│   ├── Services/
│   │   ├── PlaidService.php              # Existing — no changes
│   │   ├── OllamaService.php            # Create — HTTP client, prompt builder, context assembler
│   │   └── FinancialContextBuilder.php  # Create — builds dynamic financial context from DB
│   └── Mail/
│       ├── DailySummaryMail.php          # Create — daily summary email
│       └── PaymentReminderMail.php       # Create — bill payment reminder email
├── resources/views/
│   ├── livewire/
│   │   └── chat/
│   │       ├── chat-page.blade.php       # Create — chat page SFC with conversation list + messages
│   │       └── message-input.blade.php   # Create — message input SFC with submit
│   ├── pages/
│   │   └── chat.blade.php               # Modify — replace placeholder with chat components
│   └── mail/
│       ├── daily-summary.blade.php       # Create — email template
│       └── payment-reminder.blade.php    # Create — email template
├── config/
│   └── queue.php                         # Modify — add ai queue connection
├── routes/
│   └── console.php                       # Modify — add weekly/monthly summary schedules
└── tests/
    ├── Unit/
    │   └── Services/
    │       ├── OllamaServiceTest.php     # Create
    │       └── FinancialContextBuilderTest.php  # Create
    └── Feature/
        ├── Jobs/
        │   ├── CategorizationJobTest.php # Create
        │   ├── BudgetCheckJobTest.php    # Create
        │   ├── HealthCheckJobTest.php    # Create
        │   ├── PaymentOptimizerJobTest.php  # Create
        │   └── SummaryJobTest.php        # Create
        └── Livewire/
            └── ChatTest.php              # Create
```

---

### Task 1: OllamaService — HTTP Client

**Files:**
- Create: `steward/app/Services/OllamaService.php`
- Test: `steward/tests/Unit/Services/OllamaServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Unit/Services/OllamaServiceTest.php
namespace Tests\Unit\Services;

use App\Services\OllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    private OllamaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ollama.host' => 'http://ollama:11434']);
        config(['services.ollama.model' => 'llama3.1:70b-instruct-q4_K_M']);
        $this->service = new OllamaService();
    }

    public function test_chat_sends_request_and_returns_response(): void
    {
        Http::fake([
            'ollama:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'This is a coffee purchase.'],
                'done' => true,
            ]),
        ]);

        $result = $this->service->chat(
            systemPrompt: 'You are a financial advisor.',
            messages: [['role' => 'user', 'content' => 'What is this transaction?']],
        );

        $this->assertEquals('This is a coffee purchase.', $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://ollama:11434/api/chat'
                && $request['model'] === 'llama3.1:70b-instruct-q4_K_M'
                && $request['stream'] === false
                && $request['messages'][0]['role'] === 'system';
        });
    }

    public function test_chat_json_mode_returns_parsed_array(): void
    {
        Http::fake([
            'ollama:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"category": "Coffee", "confidence": 0.95}'],
                'done' => true,
            ]),
        ]);

        $result = $this->service->chatJson(
            systemPrompt: 'Categorize this transaction. Respond in JSON.',
            messages: [['role' => 'user', 'content' => 'STARBUCKS $5.40']],
        );

        $this->assertIsArray($result);
        $this->assertEquals('Coffee', $result['category']);
        $this->assertEquals(0.95, $result['confidence']);

        Http::assertSent(fn ($request) => $request['format'] === 'json');
    }

    public function test_generate_sends_single_prompt(): void
    {
        Http::fake([
            'ollama:11434/api/generate' => Http::response([
                'response' => 'Your spending looks healthy this week.',
                'done' => true,
            ]),
        ]);

        $result = $this->service->generate(
            prompt: 'Analyze my spending this week.',
            system: 'You are a financial advisor.',
        );

        $this->assertEquals('Your spending looks healthy this week.', $result);
    }

    public function test_stream_chat_yields_tokens(): void
    {
        $streamBody = implode("\n", [
            json_encode(['message' => ['content' => 'Hello'], 'done' => false]),
            json_encode(['message' => ['content' => ' world'], 'done' => false]),
            json_encode(['message' => ['content' => '!'], 'done' => true]),
        ]);

        Http::fake([
            'ollama:11434/api/chat' => Http::response($streamBody, 200),
        ]);

        $tokens = [];
        $this->service->streamChat(
            systemPrompt: 'You are helpful.',
            messages: [['role' => 'user', 'content' => 'Hi']],
            onToken: function (string $token) use (&$tokens) {
                $tokens[] = $token;
            },
        );

        $this->assertEquals(['Hello', ' world', '!'], $tokens);

        Http::assertSent(fn ($request) => $request['stream'] === true);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="OllamaServiceTest"
```

Expected: FAIL — `OllamaService` class doesn't exist.

- [ ] **Step 3: Add Ollama config to services.php**

Add to `steward/config/services.php` inside the return array:

```php
'ollama' => [
    'host' => env('OLLAMA_HOST', 'http://ollama:11434'),
    'model' => env('OLLAMA_MODEL', 'llama3.1:70b-instruct-q4_K_M'),
],
```

- [ ] **Step 4: Implement OllamaService**

```php
<?php
// steward/app/Services/OllamaService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    private string $host;
    private string $model;

    public function __construct()
    {
        $this->host = rtrim(config('services.ollama.host', 'http://ollama:11434'), '/');
        $this->model = config('services.ollama.model', 'llama3.1:70b-instruct-q4_K_M');
    }

    public function chat(string $systemPrompt, array $messages): string
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages,
        );

        $response = Http::timeout(300)->post("{$this->host}/api/chat", [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ]);

        return $response->json('message.content', '');
    }

    public function chatJson(string $systemPrompt, array $messages): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages,
        );

        $response = Http::timeout(300)->post("{$this->host}/api/chat", [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'format' => 'json',
        ]);

        $content = $response->json('message.content', '{}');

        return json_decode($content, true) ?? [];
    }

    public function generate(string $prompt, string $system = ''): string
    {
        $body = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if ($system) {
            $body['system'] = $system;
        }

        $response = Http::timeout(300)->post("{$this->host}/api/generate", $body);

        return $response->json('response', '');
    }

    public function streamChat(string $systemPrompt, array $messages, callable $onToken): void
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages,
        );

        $response = Http::timeout(300)->post("{$this->host}/api/chat", [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ]);

        $lines = explode("\n", $response->body());

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $data = json_decode($line, true);
            if (! $data) {
                continue;
            }

            $token = $data['message']['content'] ?? '';
            if ($token !== '') {
                $onToken($token);
            }
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="OllamaServiceTest"
```

Expected: All 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add steward/app/Services/OllamaService.php steward/tests/Unit/Services/OllamaServiceTest.php steward/config/services.php
git commit -m "feat: add OllamaService with chat, JSON mode, generate, and streaming"
```

---

### Task 2: FinancialContextBuilder

**Files:**
- Create: `steward/app/Services/FinancialContextBuilder.php`
- Test: `steward/tests/Unit/Services/FinancialContextBuilderTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Unit/Services/FinancialContextBuilderTest.php
namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private FinancialContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FinancialContextBuilder();
    }

    public function test_system_prompt_includes_income_sources(): void
    {
        IncomeSource::factory()->create(['name' => 'Main Job', 'amount' => 2400, 'frequency' => 'biweekly']);
        IncomeSource::factory()->create(['name' => 'Church', 'amount' => 700, 'frequency' => 'biweekly']);

        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('Main Job', $prompt);
        $this->assertStringContainsString('Church', $prompt);
        $this->assertStringContainsString('2,400.00', $prompt);
        $this->assertStringContainsString('biweekly', $prompt);
    }

    public function test_system_prompt_includes_budget_ratios(): void
    {
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('50%', $prompt);
        $this->assertStringContainsString('30%', $prompt);
        $this->assertStringContainsString('20%', $prompt);
    }

    public function test_system_prompt_includes_categories_with_essential_flag(): void
    {
        Category::factory()->create(['name' => 'Mortgage', 'is_essential' => true, 'default_bucket' => 'needs']);
        Category::factory()->create(['name' => 'Hobbies', 'is_essential' => false, 'default_bucket' => 'wants']);

        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('Mortgage', $prompt);
        $this->assertStringContainsString('essential', $prompt);
        $this->assertStringContainsString('Hobbies', $prompt);
    }

    public function test_system_prompt_includes_behavioral_rules(): void
    {
        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('biblical', $prompt);
        $this->assertStringContainsString('stewardship', $prompt);
        $this->assertStringContainsString('discretionary', $prompt);
    }

    public function test_dynamic_context_includes_account_balances(): void
    {
        $account = Account::factory()->create([
            'name' => 'Checking',
            'current_balance' => 1247.33,
            'available_balance' => 1200.00,
        ]);

        $context = $this->builder->buildDynamicContext();

        $this->assertStringContainsString('Checking', $context);
        $this->assertStringContainsString('1,247.33', $context);
    }

    public function test_dynamic_context_includes_recent_transactions(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Starbucks',
            'amount' => 5.40,
            'date' => now()->subDay(),
        ]);

        $context = $this->builder->buildDynamicContext();

        $this->assertStringContainsString('Starbucks', $context);
        $this->assertStringContainsString('5.40', $context);
    }

    public function test_dynamic_context_includes_budget_vs_actual(): void
    {
        $category = Category::factory()->create(['name' => 'Dining Out', 'default_bucket' => 'wants']);
        Budget::factory()->create([
            'category_id' => $category->id,
            'month' => now()->format('Y-m'),
            'budgeted_amount' => 300,
            'bucket' => 'wants',
        ]);

        $account = Account::factory()->create();
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 180,
            'date' => now(),
            'budget_bucket' => 'wants',
        ]);

        $context = $this->builder->buildDynamicContext();

        $this->assertStringContainsString('Dining Out', $context);
        $this->assertStringContainsString('300.00', $context);
        $this->assertStringContainsString('180.00', $context);
    }

    public function test_categorization_prompt_lists_available_categories(): void
    {
        Category::factory()->create(['name' => 'Groceries', 'default_bucket' => 'needs']);
        Category::factory()->create(['name' => 'Coffee', 'default_bucket' => 'wants']);

        $prompt = $this->builder->buildCategorizationPrompt();

        $this->assertStringContainsString('Groceries', $prompt);
        $this->assertStringContainsString('Coffee', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('confidence', $prompt);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="FinancialContextBuilderTest"
```

Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Implement FinancialContextBuilder**

```php
<?php
// steward/app/Services/FinancialContextBuilder.php
namespace App\Services;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Summary;
use App\Models\Transaction;

class FinancialContextBuilder
{
    public function buildSystemPrompt(): string
    {
        $sections = [];

        $sections[] = $this->buildRoleSection();
        $sections[] = $this->buildIncomeSection();
        $sections[] = $this->buildBudgetRatiosSection();
        $sections[] = $this->buildCategoriesSection();
        $sections[] = $this->buildBehavioralRules();

        return implode("\n\n", array_filter($sections));
    }

    public function buildDynamicContext(): string
    {
        $sections = [];

        $sections[] = $this->buildAccountBalances();
        $sections[] = $this->buildRecentTransactions();
        $sections[] = $this->buildBudgetVsActual();
        $sections[] = $this->buildFlaggedTransactions();

        return implode("\n\n", array_filter($sections));
    }

    public function buildCategorizationPrompt(): string
    {
        $categories = Category::all();

        $categoryList = $categories->map(function ($cat) {
            $bucket = $cat->default_bucket->value;
            $essential = $cat->is_essential ? ' (essential)' : '';
            return "- {$cat->name} [bucket: {$bucket}]{$essential}";
        })->implode("\n");

        return <<<PROMPT
You are a transaction categorizer for a personal finance app.

Given a transaction, assign it to ONE of these categories:
{$categoryList}

Respond in JSON format:
{
  "category_name": "exact category name from list above",
  "confidence": 0.0 to 1.0,
  "budget_bucket": "needs" | "wants" | "savings"
}

Rules:
- Match the category_name EXACTLY as listed above.
- Set confidence based on how certain you are (1.0 = certain, 0.5 = unsure).
- If the transaction doesn't clearly fit any category, use "Uncategorized" with low confidence.
- Consider the merchant name and description when categorizing.
PROMPT;
    }

    private function buildRoleSection(): string
    {
        return <<<PROMPT
You are StewardAI, a personal financial advisor for a Christian household. You provide practical, honest financial advice from a biblical stewardship perspective. You help this family be wise stewards of their finances — building stability, reducing volatility, and planning for the future while still enjoying life.

The household consists of a married couple with two kids. They own a home and need to plan for cars (paid in cash), renovations, repairs, car maintenance, and all of life's expenses. They also have hobbies they want to enjoy responsibly.
PROMPT;
    }

    private function buildIncomeSection(): string
    {
        $sources = IncomeSource::where('is_active', true)->get();

        if ($sources->isEmpty()) {
            return 'INCOME: No income sources configured yet.';
        }

        $totalMonthly = $sources->sum(fn ($s) => $s->monthlyAmount());
        $lines = $sources->map(fn ($s) => "- {$s->name}: \${$this->fmt($s->amount)} {$s->frequency} (≈ \${$this->fmt($s->monthlyAmount())}/mo, next pay: {$s->next_pay_date->format('M j, Y')})");

        return "INCOME SOURCES:\n" . $lines->implode("\n") . "\nTotal monthly income: \${$this->fmt($totalMonthly)}";
    }

    private function buildBudgetRatiosSection(): string
    {
        $ratios = AppSetting::getValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        return "BUDGET RULE: {$ratios['needs']}% needs / {$ratios['wants']}% wants / {$ratios['savings']}% savings";
    }

    private function buildCategoriesSection(): string
    {
        $categories = Category::all();

        if ($categories->isEmpty()) {
            return '';
        }

        $lines = $categories->map(function ($cat) {
            $flags = [];
            if ($cat->is_essential) {
                $flags[] = 'essential';
            }
            $flagStr = $flags ? ' (' . implode(', ', $flags) . ')' : '';
            return "- {$cat->name} [{$cat->default_bucket->value}]{$flagStr}";
        });

        return "SPENDING CATEGORIES:\n" . $lines->implode("\n");
    }

    private function buildBehavioralRules(): string
    {
        return <<<RULES
BEHAVIORAL RULES:
- Be critical of bad spending habits. Do NOT normalize discretionary overspending.
- For discretionary categories trending over budget: challenge the behavior, connect overspending to its impact on goals. Recommend pulling back.
- For essential categories trending above budget: accept reality, recommend increasing the budget, and identify where to offset the increase from discretionary spending.
- Connect every spending decision to concrete goal impact (e.g., "That's $200 less toward your car fund").
- Provide advice from a biblical stewardship perspective. Be encouraging but honest.
- Maximize fun, curiosity, and hobbies within responsible boundaries.
- When helping plan purchases, give specific dates based on pay schedule and cash flow.
- Be direct and practical. No generic platitudes.
RULES;
    }

    private function buildAccountBalances(): string
    {
        $accounts = Account::all();

        if ($accounts->isEmpty()) {
            return 'ACCOUNT BALANCES: No accounts connected.';
        }

        $lines = $accounts->map(fn ($a) => "- {$a->name} ({$a->type->value}): \${$this->fmt($a->current_balance)} current, \${$this->fmt($a->available_balance)} available");

        return "CURRENT ACCOUNT BALANCES:\n" . $lines->implode("\n");
    }

    private function buildRecentTransactions(): string
    {
        $transactions = Transaction::with(['account', 'category'])
            ->where('date', '>=', now()->subDays(30))
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();

        if ($transactions->isEmpty()) {
            return 'RECENT TRANSACTIONS: None in the last 30 days.';
        }

        $lines = $transactions->map(function ($t) {
            $cat = $t->category?->name ?? 'Uncategorized';
            $merchant = $t->merchant_name ?? $t->description;
            return "- {$t->date->format('M j')}: {$merchant} — \${$this->fmt($t->amount)} [{$cat}]";
        });

        return "RECENT TRANSACTIONS (last 30 days):\n" . $lines->implode("\n");
    }

    private function buildBudgetVsActual(): string
    {
        $month = now()->format('Y-m');
        $budgets = Budget::with('category')->where('month', $month)->get();

        if ($budgets->isEmpty()) {
            return 'BUDGET VS ACTUAL: No budgets set for this month.';
        }

        $lines = $budgets->map(function ($b) use ($month) {
            $spent = Transaction::where('category_id', $b->category_id)
                ->whereRaw("to_char(date, 'YYYY-MM') = ?", [$month])
                ->sum('amount');
            $remaining = $b->budgeted_amount - $spent;
            $status = $remaining < 0 ? 'OVER' : 'OK';

            return "- {$b->category->name}: \${$this->fmt($spent)} / \${$this->fmt($b->budgeted_amount)} ({$status}, \${$this->fmt(abs($remaining))} " . ($remaining < 0 ? 'over' : 'remaining') . ")";
        });

        return "BUDGET VS ACTUAL (this month):\n" . $lines->implode("\n");
    }

    private function buildFlaggedTransactions(): string
    {
        $flagged = Transaction::with('account')
            ->where('needs_review', true)
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        if ($flagged->isEmpty()) {
            return '';
        }

        $lines = $flagged->map(fn ($t) => "- {$t->date->format('M j')}: {$t->merchant_name ?? $t->description} — \${$this->fmt($t->amount)} (confidence: {$t->categorization_confidence})");

        return "FLAGGED FOR REVIEW:\n" . $lines->implode("\n");
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="FinancialContextBuilderTest"
```

Expected: All 8 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add steward/app/Services/FinancialContextBuilder.php steward/tests/Unit/Services/FinancialContextBuilderTest.php
git commit -m "feat: add FinancialContextBuilder for system prompts and dynamic financial context"
```

---

### Task 3: CategorizationJob

**Files:**
- Create: `steward/app/Jobs/CategorizationJob.php`
- Test: `steward/tests/Feature/Jobs/CategorizationJobTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Feature/Jobs/CategorizationJobTest.php
namespace Tests\Feature\Jobs;

use App\Jobs\CategorizationJob;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CategorizationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_categorizes_transactions_above_threshold(): void
    {
        AppSetting::setValue('categorization_confidence_threshold', 0.9);

        $category = Category::factory()->create(['name' => 'Coffee', 'default_bucket' => 'wants']);
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Starbucks',
            'description' => 'STARBUCKS COFFEE',
            'needs_review' => true,
            'category_id' => null,
        ]);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'category_name' => 'Coffee',
                'confidence' => 0.95,
                'budget_bucket' => 'wants',
            ]);

        $this->app->instance(OllamaService::class, $mock);

        (new CategorizationJob([$transaction->id]))->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        $transaction->refresh();
        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertEquals(0.95, (float) $transaction->categorization_confidence);
        $this->assertFalse($transaction->needs_review);
        $this->assertEquals('wants', $transaction->budget_bucket->value);
    }

    public function test_flags_transactions_below_threshold(): void
    {
        AppSetting::setValue('categorization_confidence_threshold', 0.9);

        Category::factory()->create(['name' => 'Uncategorized', 'default_bucket' => 'wants']);
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'AMZN*2847XK',
            'needs_review' => true,
            'category_id' => null,
        ]);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'category_name' => 'Uncategorized',
                'confidence' => 0.45,
                'budget_bucket' => 'wants',
            ]);

        $this->app->instance(OllamaService::class, $mock);

        (new CategorizationJob([$transaction->id]))->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        $transaction->refresh();
        $this->assertEquals(0.45, (float) $transaction->categorization_confidence);
        $this->assertTrue($transaction->needs_review);
    }

    public function test_skips_already_categorized_transactions(): void
    {
        $category = Category::factory()->create(['name' => 'Coffee', 'default_bucket' => 'wants']);
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'needs_review' => false,
            'categorization_confidence' => 1.0,
        ]);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldNotReceive('chatJson');

        $this->app->instance(OllamaService::class, $mock);

        (new CategorizationJob([$transaction->id]))->handle(
            $mock,
            new FinancialContextBuilder(),
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="CategorizationJobTest"
```

Expected: FAIL.

- [ ] **Step 3: Implement CategorizationJob**

```php
<?php
// steward/app/Jobs/CategorizationJob.php
namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CategorizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public array $transactionIds,
    ) {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $context): void
    {
        $threshold = (float) AppSetting::getValue('categorization_confidence_threshold', 0.9);
        $systemPrompt = $context->buildCategorizationPrompt();

        $transactions = Transaction::whereIn('id', $this->transactionIds)
            ->where('needs_review', true)
            ->whereNull('category_id')
            ->get();

        foreach ($transactions as $transaction) {
            $this->categorizeTransaction($transaction, $ollama, $systemPrompt, $threshold);
        }
    }

    private function categorizeTransaction(
        Transaction $transaction,
        OllamaService $ollama,
        string $systemPrompt,
        float $threshold,
    ): void {
        $userMessage = "Transaction: {$transaction->merchant_name ?? $transaction->description}\n"
            . "Amount: \${$transaction->amount}\n"
            . "Date: {$transaction->date->format('Y-m-d')}";

        if ($transaction->plaid_category) {
            $userMessage .= "\nPlaid category hint: {$transaction->plaid_category}";
        }

        $result = $ollama->chatJson(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
        );

        $categoryName = $result['category_name'] ?? 'Uncategorized';
        $confidence = (float) ($result['confidence'] ?? 0);
        $bucket = $result['budget_bucket'] ?? null;

        $category = Category::where('name', $categoryName)->first();

        $transaction->update([
            'category_id' => $category?->id,
            'categorization_confidence' => $confidence,
            'needs_review' => $confidence < $threshold,
            'budget_bucket' => $bucket ?? $category?->default_bucket?->value,
        ]);

        Log::info('Transaction categorized', [
            'transaction_id' => $transaction->id,
            'category' => $categoryName,
            'confidence' => $confidence,
            'flagged' => $confidence < $threshold,
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="CategorizationJobTest"
```

Expected: All 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add steward/app/Jobs/CategorizationJob.php steward/tests/Feature/Jobs/CategorizationJobTest.php
git commit -m "feat: add CategorizationJob — auto-categorize transactions via Ollama with confidence threshold"
```

---

### Task 4: BudgetCheckJob

**Files:**
- Create: `steward/app/Jobs/BudgetCheckJob.php`
- Test: `steward/tests/Feature/Jobs/BudgetCheckJobTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Feature/Jobs/BudgetCheckJobTest.php
namespace Tests\Feature\Jobs;

use App\Jobs\BudgetCheckJob;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BudgetCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_budget_analysis(): void
    {
        AppSetting::setValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);

        $category = Category::factory()->create(['name' => 'Dining Out', 'default_bucket' => 'wants']);
        Budget::factory()->create([
            'category_id' => $category->id,
            'month' => now()->format('Y-m'),
            'budgeted_amount' => 300,
            'bucket' => 'wants',
        ]);

        $account = Account::factory()->create();
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 450,
            'date' => now(),
            'budget_bucket' => 'wants',
        ]);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn('Dining Out is $150 over budget. Consider cutting back.');

        $this->app->instance(OllamaService::class, $mock);

        $result = (new BudgetCheckJob())->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('cutting back', $result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="BudgetCheckJobTest"
```

- [ ] **Step 3: Implement BudgetCheckJob**

```php
<?php
// steward/app/Jobs/BudgetCheckJob.php
namespace App\Jobs;

use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BudgetCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): string
    {
        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<MSG
Perform a budget check for the current month.

{$dynamicContext}

Analyze:
1. How is each category tracking against its budget?
2. How are the 50/30/20 bucket totals performing?
3. Which categories are over budget and by how much?
4. What specific, actionable adjustments should be made?

Be direct and specific with dollar amounts. Connect overspending to goal impact.
MSG;

        return $ollama->chat(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="BudgetCheckJobTest"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add steward/app/Jobs/BudgetCheckJob.php steward/tests/Feature/Jobs/BudgetCheckJobTest.php
git commit -m "feat: add BudgetCheckJob — 50-30-20 analysis with Ollama"
```

---

### Task 5: HealthCheckJob

**Files:**
- Create: `steward/app/Jobs/HealthCheckJob.php`
- Test: `steward/tests/Feature/Jobs/HealthCheckJobTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Feature/Jobs/HealthCheckJobTest.php
namespace Tests\Feature\Jobs;

use App\Jobs\HealthCheckJob;
use App\Models\IncomeSource;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HealthCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_health_check_analysis(): void
    {
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn('Your spending habits show improvement. Electric bill trending up — consider increasing budget.');

        $this->app->instance(OllamaService::class, $mock);

        $result = (new HealthCheckJob())->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('improvement', $result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="HealthCheckJobTest"
```

- [ ] **Step 3: Implement HealthCheckJob**

```php
<?php
// steward/app/Jobs/HealthCheckJob.php
namespace App\Jobs;

use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): string
    {
        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<MSG
Perform a financial health check for this household.

{$dynamicContext}

Analyze:
1. Spending habits and patterns — what's improving? What's getting worse?
2. For essential categories trending above budget: recommend a specific budget increase and where to offset it from discretionary spending.
3. For discretionary categories trending above budget: challenge the behavior. Be specific about dollar amounts and goal impact.
4. Are their financial goals achievable at this spending trajectory?
5. Provide 2-3 specific, actionable recommendations from a biblical stewardship perspective.

Be honest and direct. Don't soften bad news. Connect every recommendation to specific dollar amounts.
MSG;

        return $ollama->chat(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="HealthCheckJobTest"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add steward/app/Jobs/HealthCheckJob.php steward/tests/Feature/Jobs/HealthCheckJobTest.php
git commit -m "feat: add HealthCheckJob — habit detection, budget recommendations, biblical stewardship advice"
```

---

### Task 6: PaymentOptimizerJob

**Files:**
- Create: `steward/app/Jobs/PaymentOptimizerJob.php`
- Create: `steward/app/Mail/PaymentReminderMail.php`
- Create: `steward/resources/views/mail/payment-reminder.blade.php`
- Test: `steward/tests/Feature/Jobs/PaymentOptimizerJobTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Feature/Jobs/PaymentOptimizerJobTest.php
namespace Tests\Feature\Jobs;

use App\Jobs\PaymentOptimizerJob;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PaymentOptimizerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_payment_calendar(): void
    {
        IncomeSource::factory()->create([
            'amount' => 2400,
            'frequency' => 'biweekly',
            'next_pay_date' => now()->addDays(3),
        ]);

        Category::factory()->create(['name' => 'Electric', 'is_essential' => true, 'default_bucket' => 'needs']);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'recommendations' => [
                    ['bill' => 'Electric', 'suggested_pay_date' => now()->addDays(5)->format('Y-m-d'), 'reason' => 'Pay after your paycheck clears'],
                ],
                'analysis' => 'Spreading bills across pay periods keeps your balance stable.',
            ]);

        $this->app->instance(OllamaService::class, $mock);

        $result = (new PaymentOptimizerJob())->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('analysis', $result);
        $this->assertCount(1, $result['recommendations']);
    }

    public function test_sends_payment_reminder_emails(): void
    {
        Mail::fake();
        AppSetting::setValue('email_recipients', ['jamie@test.com']);

        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly', 'next_pay_date' => now()->addDays(3)]);
        Category::factory()->create(['name' => 'Electric', 'is_essential' => true, 'default_bucket' => 'needs']);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'recommendations' => [
                    ['bill' => 'Electric', 'suggested_pay_date' => now()->format('Y-m-d'), 'reason' => 'Due today'],
                ],
                'analysis' => 'Pay your electric bill today.',
            ]);

        $this->app->instance(OllamaService::class, $mock);

        (new PaymentOptimizerJob())->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        Mail::assertSent(\App\Mail\PaymentReminderMail::class);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="PaymentOptimizerJobTest"
```

- [ ] **Step 3: Create PaymentReminderMail**

```php
<?php
// steward/app/Mail/PaymentReminderMail.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $recommendations,
        public string $analysis,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'StewardAI — Payment Reminders for Today',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-reminder',
        );
    }
}
```

- [ ] **Step 4: Create email template**

```blade
{{-- steward/resources/views/mail/payment-reminder.blade.php --}}
<h2>Payment Reminders</h2>

<p>{{ $analysis }}</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
    <thead>
        <tr style="border-bottom: 2px solid #e5e7eb;">
            <th style="text-align: left; padding: 8px;">Bill</th>
            <th style="text-align: left; padding: 8px;">Suggested Date</th>
            <th style="text-align: left; padding: 8px;">Reason</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($recommendations as $rec)
            <tr style="border-bottom: 1px solid #f3f4f6;">
                <td style="padding: 8px; font-weight: 600;">{{ $rec['bill'] }}</td>
                <td style="padding: 8px;">{{ $rec['suggested_pay_date'] }}</td>
                <td style="padding: 8px; color: #6b7280;">{{ $rec['reason'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top: 24px; color: #9ca3af; font-size: 14px;">— StewardAI</p>
```

- [ ] **Step 5: Implement PaymentOptimizerJob**

```php
<?php
// steward/app/Jobs/PaymentOptimizerJob.php
namespace App\Jobs;

use App\Mail\PaymentReminderMail;
use App\Models\AppSetting;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class PaymentOptimizerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): array
    {
        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<MSG
Analyze the household's essential bills and optimize payment timing.

{$dynamicContext}

Based on the pay schedule and account balances, recommend optimal payment dates for essential bills that:
1. Spread payments across pay periods instead of clustering them
2. Keep the checking account balance as stable as possible throughout the month
3. Account for when each paycheck arrives

Respond in JSON format:
{
  "recommendations": [
    {
      "bill": "category name",
      "suggested_pay_date": "YYYY-MM-DD",
      "reason": "brief explanation"
    }
  ],
  "analysis": "Overall analysis of the payment schedule optimization"
}

Only include bills that should be paid in the next 14 days. If a bill is on autopay with no flexibility, skip it.
MSG;

        $result = $ollama->chatJson(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
        );

        $recommendations = $result['recommendations'] ?? [];
        $analysis = $result['analysis'] ?? '';

        $this->sendReminders($recommendations, $analysis);

        return $result;
    }

    private function sendReminders(array $recommendations, string $analysis): void
    {
        $todayReminders = collect($recommendations)->filter(function ($rec) {
            return ($rec['suggested_pay_date'] ?? '') === now()->format('Y-m-d');
        })->values()->all();

        if (empty($todayReminders)) {
            return;
        }

        $recipients = AppSetting::getValue('email_recipients', []);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new PaymentReminderMail(
                recommendations: $todayReminders,
                analysis: $analysis,
            ));
        }
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="PaymentOptimizerJobTest"
```

Expected: All 2 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Jobs/PaymentOptimizerJob.php steward/app/Mail/PaymentReminderMail.php steward/resources/views/mail/payment-reminder.blade.php steward/tests/Feature/Jobs/PaymentOptimizerJobTest.php
git commit -m "feat: add PaymentOptimizerJob — bill timing optimization with email reminders"
```

---

### Task 7: SummaryJob

**Files:**
- Create: `steward/app/Jobs/SummaryJob.php`
- Create: `steward/app/Mail/DailySummaryMail.php`
- Create: `steward/resources/views/mail/daily-summary.blade.php`
- Test: `steward/tests/Feature/Jobs/SummaryJobTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Feature/Jobs/SummaryJobTest.php
namespace Tests\Feature\Jobs;

use App\Jobs\SummaryJob;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Summary;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SummaryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_daily_summary(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create(['name' => 'Coffee', 'default_bucket' => 'wants']);
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 5.40,
            'date' => now(),
            'budget_bucket' => 'wants',
        ]);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn('You spent $5.40 on coffee today. Moderate spending.');

        $this->app->instance(OllamaService::class, $mock);

        (new SummaryJob('daily'))->handle(
            $mock,
            new FinancialContextBuilder(),
        );

        $summary = Summary::where('type', 'daily')->first();
        $this->assertNotNull($summary);
        $this->assertEquals(5.40, (float) $summary->total_spent);
        $this->assertEquals(5.40, (float) $summary->wants_spent);
        $this->assertEquals(0, (float) $summary->needs_spent);
        $this->assertStringContainsString('coffee', $summary->ai_analysis);
    }

    public function test_creates_weekly_summary(): void
    {
        $account = Account::factory()->create();
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 100,
            'date' => now()->subDays(2),
            'budget_bucket' => 'needs',
        ]);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chat')->once()->andReturn('Quiet week. $100 in needs.');

        $this->app->instance(OllamaService::class, $mock);

        (new SummaryJob('weekly'))->handle($mock, new FinancialContextBuilder());

        $summary = Summary::where('type', 'weekly')->first();
        $this->assertNotNull($summary);
        $this->assertEquals(100, (float) $summary->needs_spent);
    }

    public function test_sends_summary_email(): void
    {
        Mail::fake();
        AppSetting::setValue('email_recipients', ['jamie@test.com', 'wife@test.com']);
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('chat')->once()->andReturn('Daily summary.');

        $this->app->instance(OllamaService::class, $mock);

        (new SummaryJob('daily'))->handle($mock, new FinancialContextBuilder());

        Mail::assertSent(\App\Mail\DailySummaryMail::class, 2);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="SummaryJobTest"
```

- [ ] **Step 3: Create DailySummaryMail**

```php
<?php
// steward/app/Mail/DailySummaryMail.php
namespace App\Mail;

use App\Models\Summary;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Summary $summary,
    ) {}

    public function envelope(): Envelope
    {
        $label = ucfirst($this->summary->type);
        $date = $this->summary->period_start->format('M j, Y');

        return new Envelope(
            subject: "StewardAI — {$label} Summary ({$date})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-summary',
        );
    }
}
```

- [ ] **Step 4: Create email template**

```blade
{{-- steward/resources/views/mail/daily-summary.blade.php --}}
<h2>{{ ucfirst($summary->type) }} Financial Summary</h2>
<p style="color: #6b7280;">{{ $summary->period_start->format('M j, Y') }} — {{ $summary->period_end->format('M j, Y') }}</p>

<table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <tr>
        <td style="padding: 12px; background: #f9fafb; border-radius: 8px;">
            <strong>Total Spent</strong><br>${{ number_format($summary->total_spent, 2) }}
        </td>
        <td style="padding: 12px; background: #f9fafb; border-radius: 8px;">
            <strong>Needs</strong><br>${{ number_format($summary->needs_spent, 2) }}
        </td>
        <td style="padding: 12px; background: #f9fafb; border-radius: 8px;">
            <strong>Wants</strong><br>${{ number_format($summary->wants_spent, 2) }}
        </td>
        <td style="padding: 12px; background: #f9fafb; border-radius: 8px;">
            <strong>Savings</strong><br>${{ number_format($summary->savings_spent, 2) }}
        </td>
    </tr>
</table>

<h3>Analysis</h3>
<p>{{ $summary->ai_analysis }}</p>

@if ($summary->ai_advice)
    <h3>Advice</h3>
    <p>{{ $summary->ai_advice }}</p>
@endif

<p style="margin-top: 24px; color: #9ca3af; font-size: 14px;">— StewardAI</p>
```

- [ ] **Step 5: Implement SummaryJob**

```php
<?php
// steward/app/Jobs/SummaryJob.php
namespace App\Jobs;

use App\Mail\DailySummaryMail;
use App\Models\AppSetting;
use App\Models\IncomeSource;
use App\Models\Summary;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public string $type = 'daily',
    ) {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): void
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates();

        $transactions = Transaction::whereBetween('date', [$periodStart, $periodEnd])->get();

        $totalSpent = $transactions->sum('amount');
        $needsSpent = $transactions->where('budget_bucket', 'needs')->sum('amount');
        $wantsSpent = $transactions->where('budget_bucket', 'wants')->sum('amount');
        $savingsSpent = $transactions->where('budget_bucket', 'savings')->sum('amount');

        $totalMonthlyIncome = IncomeSource::where('is_active', true)->get()->sum(fn ($s) => $s->monthlyAmount());

        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<MSG
Generate a {$this->type} financial summary for {$periodStart->format('M j')} — {$periodEnd->format('M j, Y')}.

{$dynamicContext}

Period totals:
- Total spent: \${$totalSpent}
- Needs: \${$needsSpent}
- Wants: \${$wantsSpent}
- Savings: \${$savingsSpent}

Provide:
1. A concise analysis of the spending (2-3 sentences)
2. Any concerns or highlights
3. Brief biblical stewardship advice relevant to this period's patterns
MSG;

        $analysis = $ollama->chat(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
        );

        $summary = Summary::updateOrCreate(
            ['type' => $this->type, 'period_start' => $periodStart],
            [
                'period_end' => $periodEnd,
                'total_income' => $totalMonthlyIncome,
                'total_spent' => $totalSpent,
                'needs_spent' => $needsSpent,
                'wants_spent' => $wantsSpent,
                'savings_spent' => $savingsSpent,
                'ai_analysis' => $analysis,
            ],
        );

        $this->sendEmails($summary);
    }

    private function getPeriodDates(): array
    {
        return match ($this->type) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function sendEmails(Summary $summary): void
    {
        $recipients = AppSetting::getValue('email_recipients', []);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new DailySummaryMail($summary));
        }
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="SummaryJobTest"
```

Expected: All 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Jobs/SummaryJob.php steward/app/Mail/DailySummaryMail.php steward/resources/views/mail/daily-summary.blade.php steward/tests/Feature/Jobs/SummaryJobTest.php
git commit -m "feat: add SummaryJob — daily/weekly/monthly summary generation with email delivery"
```

---

### Task 8: Job Chaining and Scheduler Updates

**Files:**
- Modify: `steward/app/Jobs/PlaidSyncJob.php`
- Modify: `steward/routes/console.php`

- [ ] **Step 1: Update PlaidSyncJob to chain CategorizationJob after sync**

At the end of the `handle()` method in `steward/app/Jobs/PlaidSyncJob.php`, after the `Log::info()` call, add:

```php
// Collect IDs of newly added transactions that need categorization
$newTransactionIds = Transaction::where('needs_review', true)
    ->whereNull('category_id')
    ->pluck('id')
    ->toArray();

if (! empty($newTransactionIds)) {
    CategorizationJob::dispatch($newTransactionIds);
}
```

Add the import at the top: `use App\Jobs\CategorizationJob;`

- [ ] **Step 2: Update the scheduler to chain the full pipeline and add weekly/monthly summaries**

Replace `steward/routes/console.php` with:

```php
<?php
use App\Jobs\BudgetCheckJob;
use App\Jobs\HealthCheckJob;
use App\Jobs\PaymentOptimizerJob;
use App\Jobs\PlaidSyncJob;
use App\Jobs\SummaryJob;
use App\Models\AppSetting;
use App\Models\PlaidConnection;
use Illuminate\Support\Facades\Schedule;

// Daily sync pipeline
Schedule::call(function () {
    $connections = PlaidConnection::where('status', 'active')->get();

    foreach ($connections as $connection) {
        PlaidSyncJob::dispatch($connection);
    }
})->cron((function () {
    try {
        return AppSetting::getValue('sync_schedule', '0 4 * * *');
    } catch (\Throwable) {
        return '0 4 * * *';
    }
})())
  ->name('plaid-sync')
  ->withoutOverlapping();

// AI analysis pipeline (runs 30 minutes after sync to allow categorization to complete)
Schedule::call(function () {
    BudgetCheckJob::dispatch();
    HealthCheckJob::dispatch();
    PaymentOptimizerJob::dispatch();
    SummaryJob::dispatch('daily');
})->cron((function () {
    try {
        $syncCron = AppSetting::getValue('sync_schedule', '0 4 * * *');
        $parts = explode(' ', $syncCron);
        $minute = ((int) $parts[0] + 30) % 60;
        $hour = (int) $parts[1] + ((int) $parts[0] + 30 >= 60 ? 1 : 0);
        return "{$minute} {$hour} * * *";
    } catch (\Throwable) {
        return '30 4 * * *';
    }
})())
  ->name('ai-analysis-pipeline')
  ->withoutOverlapping();

// Weekly summary — every Monday at 6 AM
Schedule::command('queue:work redis --queue=ai --once')->weeklyOn(1, '06:00');
Schedule::call(fn () => SummaryJob::dispatch('weekly'))
    ->weeklyOn(1, '06:00')
    ->name('weekly-summary');

// Monthly summary — 1st of each month at 6 AM
Schedule::call(fn () => SummaryJob::dispatch('monthly'))
    ->monthlyOn(1, '06:00')
    ->name('monthly-summary');
```

- [ ] **Step 3: Configure the AI queue**

Add to `steward/config/queue.php` in the `connections.redis` section, after the existing redis connection definition, add a note comment. Actually, the `ai` queue uses the same Redis connection — it's just a different queue name. The queue name is set in each job's constructor with `$this->onQueue('ai')`. The worker entrypoint already listens on both `default,ai` queues. No config changes needed.

Verify the worker entrypoint handles both queues:

```bash
cat docker/worker/entrypoint.sh
```

Expected: `--queue=default,ai`

- [ ] **Step 4: Commit**

```bash
git add steward/app/Jobs/PlaidSyncJob.php steward/routes/console.php
git commit -m "feat: chain categorization after sync, schedule full AI pipeline with weekly/monthly summaries"
```

---

### Task 9: Chat Livewire Component

**Files:**
- Create: `steward/resources/views/livewire/chat/chat-page.blade.php`
- Create: `steward/resources/views/livewire/chat/message-input.blade.php`
- Modify: `steward/resources/views/pages/chat.blade.php`
- Test: `steward/tests/Feature/Livewire/ChatTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// steward/tests/Feature/Livewire/ChatTest.php
namespace Tests\Feature\Livewire;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_new_conversation(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('chat.chat-page')
            ->call('newConversation')
            ->assertSet('activeConversationId', ChatConversation::first()->id);

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $user->id,
            'title' => 'New Conversation',
        ]);
    }

    public function test_can_send_message_and_receive_response(): void
    {
        $user = User::factory()->create();
        $conversation = ChatConversation::create(['user_id' => $user->id, 'title' => 'Test']);

        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('streamChat')
            ->once()
            ->andReturnUsing(function ($systemPrompt, $messages, $onToken) {
                $onToken('Great ');
                $onToken('question!');
            });

        $this->app->instance(OllamaService::class, $mock);

        Livewire::actingAs($user)
            ->test('chat.chat-page', ['activeConversationId' => $conversation->id])
            ->set('messageText', 'Can I afford a coffee today?')
            ->call('sendMessage');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Can I afford a coffee today?',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
        ]);
    }

    public function test_displays_conversation_history(): void
    {
        $user = User::factory()->create();
        $conversation = ChatConversation::create(['user_id' => $user->id, 'title' => 'Test']);
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Hello!',
        ]);
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi there!',
        ]);

        Livewire::actingAs($user)
            ->test('chat.chat-page', ['activeConversationId' => $conversation->id])
            ->assertSee('Hello!')
            ->assertSee('Hi there!');
    }

    public function test_lists_user_conversations(): void
    {
        $user = User::factory()->create();
        ChatConversation::create(['user_id' => $user->id, 'title' => 'Budget Review']);
        ChatConversation::create(['user_id' => $user->id, 'title' => 'Car Purchase Plan']);

        Livewire::actingAs($user)
            ->test('chat.chat-page')
            ->assertSee('Budget Review')
            ->assertSee('Car Purchase Plan');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="ChatTest"
```

- [ ] **Step 3: Create the chat page SFC component**

```blade
{{-- steward/resources/views/livewire/chat/chat-page.blade.php --}}
<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Livewire\Component;

new class extends Component {
    public ?int $activeConversationId = null;
    public string $messageText = '';
    public string $streamingResponse = '';
    public bool $isStreaming = false;

    public function mount(?int $activeConversationId = null): void
    {
        $this->activeConversationId = $activeConversationId;

        if (! $this->activeConversationId) {
            $latest = ChatConversation::where('user_id', auth()->id())
                ->latest()
                ->first();
            $this->activeConversationId = $latest?->id;
        }
    }

    public function newConversation(): void
    {
        $conversation = ChatConversation::create([
            'user_id' => auth()->id(),
            'title' => 'New Conversation',
        ]);

        $this->activeConversationId = $conversation->id;
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
        $this->streamingResponse = '';
    }

    public function sendMessage(): void
    {
        if (trim($this->messageText) === '' || ! $this->activeConversationId) {
            return;
        }

        $conversation = ChatConversation::where('id', $this->activeConversationId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $this->messageText,
        ]);

        if ($conversation->title === 'New Conversation') {
            $conversation->update(['title' => \Str::limit($this->messageText, 50)]);
        }

        $this->messageText = '';
        $this->isStreaming = true;
        $this->streamingResponse = '';

        $contextBuilder = app(FinancialContextBuilder::class);
        $ollama = app(OllamaService::class);

        $systemPrompt = $contextBuilder->buildSystemPrompt() . "\n\n" . $contextBuilder->buildDynamicContext();

        $history = ChatMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $fullResponse = '';

        $ollama->streamChat(
            systemPrompt: $systemPrompt,
            messages: $history,
            onToken: function (string $token) use (&$fullResponse) {
                $fullResponse .= $token;
                $this->streamingResponse = $fullResponse;
            },
        );

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $fullResponse,
        ]);

        $this->isStreaming = false;
        $this->streamingResponse = '';
    }

    public function with(): array
    {
        $conversations = ChatConversation::where('user_id', auth()->id())
            ->latest()
            ->get();

        $messages = $this->activeConversationId
            ? ChatMessage::where('conversation_id', $this->activeConversationId)
                ->orderBy('created_at')
                ->get()
            : collect();

        return [
            'conversations' => $conversations,
            'messages' => $messages,
        ];
    }
};
?>

<div class="flex gap-6 h-[calc(100vh-8rem)]">
    {{-- Conversation List --}}
    <div class="w-72 flex-shrink-0 flex flex-col rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Conversations</h3>
            <button wire:click="newConversation" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <x-lucide-plus class="w-4 h-4" />
            </button>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $conv)
                <button
                    wire:click="selectConversation({{ $conv->id }})"
                    @class([
                        'w-full text-left px-4 py-3 text-sm border-b border-zinc-100 dark:border-zinc-800/50 transition-colors',
                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' => $conv->id === $activeConversationId,
                        'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/30' => $conv->id !== $activeConversationId,
                    ])>
                    <span class="block truncate">{{ $conv->title }}</span>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $conv->updated_at->diffForHumans() }}</span>
                </button>
            @empty
                <div class="p-4 text-center text-sm text-zinc-400 dark:text-zinc-500">
                    No conversations yet.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex-1 flex flex-col rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
        @if ($activeConversationId)
            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-messages">
                @foreach ($messages as $msg)
                    <div @class([
                        'flex',
                        'justify-end' => $msg->role === 'user',
                        'justify-start' => $msg->role === 'assistant',
                    ])>
                        <div @class([
                            'max-w-[75%] rounded-xl px-4 py-3 text-sm leading-relaxed',
                            'bg-blue-600 text-white' => $msg->role === 'user',
                            'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' => $msg->role === 'assistant',
                        ])>
                            {!! nl2br(e($msg->content)) !!}
                        </div>
                    </div>
                @endforeach

                @if ($isStreaming && $streamingResponse)
                    <div class="flex justify-start">
                        <div class="max-w-[75%] rounded-xl px-4 py-3 text-sm leading-relaxed bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100">
                            {!! nl2br(e($streamingResponse)) !!}
                            <span class="inline-block w-2 h-4 bg-zinc-400 dark:bg-zinc-500 animate-pulse ml-0.5"></span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Input --}}
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                <form wire:submit="sendMessage" class="flex gap-3">
                    <input
                        type="text"
                        wire:model="messageText"
                        placeholder="Ask about your finances..."
                        class="flex-1 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        @disabled($isStreaming)
                    />
                    <button
                        type="submit"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-xl transition-colors disabled:opacity-50"
                        @disabled($isStreaming || trim($messageText) === '')
                    >
                        <x-lucide-send class="w-4 h-4" />
                    </button>
                </form>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <x-lucide-message-square class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-zinc-600 dark:text-zinc-400 mb-2">Start a conversation</h3>
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mb-4">Ask about your finances, plan a purchase, or get advice.</p>
                    <button wire:click="newConversation" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-xl transition-colors">
                        New Conversation
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
```

- [ ] **Step 4: Update the chat page component**

Replace `steward/resources/views/pages/chat.blade.php`:

```blade
<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Chat')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Chat</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Talk with your financial advisor.</p>
    </div>

    <livewire:chat.chat-page />
</div>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="ChatTest"
```

Expected: All 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add steward/resources/views/livewire/chat/ steward/resources/views/pages/chat.blade.php steward/tests/Feature/Livewire/ChatTest.php
git commit -m "feat: add Chat page with conversation management, message history, and Ollama streaming"
```

---

### Task 10: Run Full Test Suite and Verify

- [ ] **Step 1: Run all tests**

```bash
docker compose exec app php artisan test
```

Expected: All tests pass — previous 56 + new tests from this phase.

- [ ] **Step 2: Verify the chat UI in browser**

1. Open `http://localhost:8080/chat`
2. Click "New Conversation"
3. Verify the conversation list and message area render correctly
4. (Note: sending messages will fail without a running Ollama instance — that's expected on the Mac dev environment)

- [ ] **Step 3: Final commit if any adjustments were needed**

```bash
git status
# If clean: no commit needed
# If fixes were applied: stage and commit with descriptive message
```
