# Flux UI Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the entire StewardAI frontend from hand-rolled Tailwind to Flux UI components while preserving all existing PHP logic, ApexCharts, and custom progress bars.

**Architecture:** Replace the app layout, sidebar, mobile dock, and all 23 Livewire component templates with Flux UI components. The PHP component classes stay untouched -- only Blade templates change. Flux handles dark mode, icons, and layout natively.

**Tech Stack:** Laravel 13, Livewire 4, Flux UI (latest), Tailwind CSS v4, Alpine.js, ApexCharts (kept), Lucide Icons (via `flux:icon`)

---

### Task 1: Install Flux UI and Configure Build Pipeline

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Delete: `tailwind.config.js`
- Modify: `vite.config.js` (if needed)

- [ ] **Step 1: Install Flux via Composer**

```bash
composer require livewire/flux
```

- [ ] **Step 2: Upgrade Tailwind CSS to v4 and remove v3 config**

The project currently uses Tailwind v3 with `tailwind.config.js`. Flux requires Tailwind v4 which uses CSS-based configuration. Update `package.json`:

```bash
npm install tailwindcss@latest @tailwindcss/vite@latest
npm uninstall @tailwindcss/forms autoprefixer postcss
```

Delete `tailwind.config.js`:
```bash
rm tailwind.config.js
```

- [ ] **Step 3: Update `resources/css/app.css`**

Replace the entire file with:

```css
@import "tailwindcss";
@import "../../vendor/livewire/flux/dist/flux.css";

@custom-variant dark (&:where(.dark, .dark *));

/* iOS 26-style bezel borders — subtle 1px edge that catches light */
.bubble-user {
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.12),
        inset 0 -1px 0 rgba(0, 0, 0, 0.06),
        0 1px 3px rgba(0, 0, 0, 0.08);
}

.bubble-assistant {
    border: 1px solid rgba(0, 0, 0, 0.04);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.7),
        inset 0 -1px 0 rgba(0, 0, 0, 0.03),
        0 1px 3px rgba(0, 0, 0, 0.04);
}

:is(.dark) .bubble-assistant {
    border: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.05),
        inset 0 -1px 0 rgba(0, 0, 0, 0.15),
        0 1px 3px rgba(0, 0, 0, 0.2);
}

/* Thinking dots animation */
@keyframes thinking {
    0%, 80%, 100% {
        transform: scale(0.6);
        opacity: 0.4;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Fade-in for new assistant messages */
.chat-fade-in {
    animation: chatFadeIn 0.4s ease-out;
}

@keyframes chatFadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

- [ ] **Step 4: Simplify `resources/js/app.js`**

Remove the custom theme store (Flux handles appearance via `$flux.appearance`). Keep ApexCharts:

```javascript
import './bootstrap';
import ApexCharts from 'apexcharts';

window.ApexCharts = ApexCharts;
```

Note: Alpine.js is bundled with Flux/Livewire -- remove the manual Alpine import.

- [ ] **Step 5: Import all Lucide icons used in the project**

```bash
php artisan flux:icon alert-circle alert-triangle arrow-left-right arrow-up banknote brain building-2 calendar check check-circle chevron-down chevron-left chevron-right chevron-up clock file-text flag grid-3x3 home landmark layout-dashboard lightbulb link loader-2 log-out mail menu message-circle message-square minus monitor moon pencil pie-chart piggy-bank plus send settings shopping-bag sliders-horizontal sparkles square-pen sun tags target trash-2 trending-down trending-up upload user wallet x
```

- [ ] **Step 6: Build and verify no errors**

```bash
npm run build
```

Expected: Build completes without errors.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: install Flux UI, upgrade Tailwind to v4, import Lucide icons"
```

---

### Task 2: Migrate App Layout, Sidebar, and Mobile Dock

**Files:**
- Rewrite: `resources/views/components/layouts/app.blade.php`
- Rewrite: `resources/views/livewire/layout/sidebar.blade.php`
- Rewrite: `resources/views/livewire/layout/mobile-dock.blade.php`
- Modify: `resources/views/layouts/guest.blade.php`

- [ ] **Step 1: Rewrite `resources/views/components/layouts/app.blade.php`**

Replace the entire file with Flux's sidebar layout:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'StewardAI' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile" class="border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
        <flux:sidebar.header>
            <flux:sidebar.brand name="Better With 90" class="text-lg font-semibold tracking-tight" />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="layout-dashboard" href="/dashboard" wire:navigate>Dashboard</flux:sidebar.item>
            <flux:sidebar.item icon="arrow-left-right" href="/transactions" wire:navigate>Transactions</flux:sidebar.item>
            <flux:sidebar.item icon="wallet" href="/budgets" wire:navigate>Budgets</flux:sidebar.item>
            <flux:sidebar.item icon="tags" href="/categories" wire:navigate>Categories</flux:sidebar.item>
            <flux:sidebar.item icon="landmark" href="/accounts" wire:navigate>Accounts</flux:sidebar.item>
            <flux:sidebar.item icon="file-text" href="/summaries" wire:navigate>Summaries</flux:sidebar.item>
            <flux:sidebar.item icon="target" href="/goals" wire:navigate>Goals</flux:sidebar.item>
            <flux:sidebar.item icon="message-square" href="/chat" wire:navigate>Chat</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="user" href="/profile" wire:navigate>Profile</flux:sidebar.item>
            <flux:sidebar.item icon="settings" href="/settings" wire:navigate>Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        {{-- Appearance toggle --}}
        <div class="px-4 py-2">
            <flux:dropdown x-data align="start" position="top">
                <flux:button variant="subtle" size="sm" class="w-full justify-start" icon="sun">Appearance</flux:button>
                <flux:menu>
                    <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                    <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                    <flux:menu.item icon="monitor" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        <flux:separator />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:sidebar.item icon="log-out" type="submit">Sign Out</flux:sidebar.item>
        </form>
    </flux:sidebar>

    {{-- Mobile header --}}
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="menu" inset="left" />
        <flux:spacer />
        <flux:dropdown x-data align="end">
            <flux:button variant="subtle" square icon="sun" aria-label="Appearance" />
            <flux:menu>
                <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                <flux:menu.item icon="monitor" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @fluxScripts
</body>
</html>
```

- [ ] **Step 2: Empty out the sidebar and mobile dock Livewire components**

Since the layout now handles everything, these components become empty shells. They still need to exist because they're persisted with `@persist`.

`resources/views/livewire/layout/sidebar.blade.php`:
```blade
<?php
use Livewire\Component;
new class extends Component {};
?>
<div></div>
```

`resources/views/livewire/layout/mobile-dock.blade.php`:
```blade
<?php
use Livewire\Component;
new class extends Component {};
?>
<div></div>
```

Note: Alternatively, remove the `@persist` blocks from the layout entirely since Flux handles the sidebar. If removing, also delete these two component files.

- [ ] **Step 3: Update `resources/views/layouts/guest.blade.php`**

Replace with Flux-styled guest layout:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'StewardAI') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 antialiased">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
        <div class="mb-6">
            <flux:heading size="xl">Better With 90</flux:heading>
        </div>
        <flux:card class="w-full sm:max-w-md">
            {{ $slot }}
        </flux:card>
    </div>
    @fluxScripts
</body>
</html>
```

- [ ] **Step 4: Verify the layout renders correctly**

```bash
php artisan serve --port=8001
```

Open http://localhost:8001/dashboard in a browser. Verify:
- Sidebar renders with all nav items
- Dark mode toggle works via the Appearance dropdown
- Mobile view shows hamburger toggle
- Main content area renders

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: migrate layout to Flux sidebar with appearance toggle"
```

---

### Task 3: Migrate Dashboard Page and Widgets

**Files:**
- Modify: `resources/views/pages/dashboard.blade.php`
- Modify: `resources/views/livewire/dashboard/balance-cards.blade.php`
- Modify: `resources/views/livewire/dashboard/budget-progress.blade.php`
- Modify: `resources/views/livewire/dashboard/flagged-transactions.blade.php`
- Modify: `resources/views/livewire/dashboard/goals-summary.blade.php`
- Modify: `resources/views/livewire/dashboard/spending-chart.blade.php`
- Modify: `resources/views/livewire/dashboard/summary-snippet.blade.php`

- [ ] **Step 1: Migrate `resources/views/pages/dashboard.blade.php`**

Replace the heading and description with Flux components. Keep the Livewire component embeds as-is:

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
        <flux:heading size="xl" level="1">Dashboard</flux:heading>
        <flux:text class="mt-1">Your financial overview.</flux:text>
    </div>

    <div class="space-y-6">
        <livewire:dashboard.balance-cards />
        <livewire:dashboard.budget-progress />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <livewire:dashboard.spending-chart />
            <livewire:dashboard.summary-snippet />
        </div>

        <livewire:dashboard.flagged-transactions />
        <livewire:dashboard.goals-summary />
    </div>
</div>
```

- [ ] **Step 2: Migrate `balance-cards.blade.php` template**

Replace only the Blade template (everything after `?>`) -- keep the PHP class untouched. Replace `<x-lucide-*>` with `<flux:icon.*>`, headings with `flux:heading`, text with `flux:text`, links with `flux:link`, and wrap cards in `flux:card`:

```blade
<div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @forelse ($accounts as $account)
            <flux:card>
                <div class="flex items-center gap-3 mb-3">
                    @if ($account->type === AccountType::Savings)
                        <flux:icon.piggy-bank class="text-zinc-400 dark:text-zinc-500" variant="mini" />
                    @else
                        <flux:icon.wallet class="text-zinc-400 dark:text-zinc-500" variant="mini" />
                    @endif
                    <flux:text size="sm">{{ $account->name }}</flux:text>
                </div>
                <flux:heading size="xl">${{ number_format($account->current_balance, 2) }}</flux:heading>
                @if ($account->available_balance !== null)
                    <flux:text size="sm" class="mt-1">${{ number_format($account->available_balance, 2) }} available</flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card class="col-span-full text-center">
                <flux:icon.wallet class="size-10 text-zinc-400 dark:text-zinc-600 mx-auto mb-3" />
                <flux:text class="mb-3">No accounts connected</flux:text>
                <flux:link href="/accounts" wire:navigate>Connect an account</flux:link>
            </flux:card>
        @endforelse

        @if ($nextPayday)
            <flux:card>
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.calendar class="text-zinc-400 dark:text-zinc-500" variant="mini" />
                    <flux:text size="sm">Next Payday</flux:text>
                </div>
                <flux:heading size="xl">{{ $daysUntilPay }} days</flux:heading>
                <flux:text size="sm" class="mt-1">{{ $nextPayday->format('M j, Y') }}</flux:text>
            </flux:card>
        @endif
    </div>
</div>
```

- [ ] **Step 3: Migrate `budget-progress.blade.php` template**

Keep the PHP class and the custom stacked bar + stat columns (custom progress bars stay). Replace only the wrapper, headings, and text:

```blade
<flux:card>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="sm">{{ now()->format('F') }} &mdash; 50 / 30 / 20 Breakdown</flux:heading>
        @if ($totalSpent > 0)
            <flux:text size="sm">${{ number_format($totalSpent, 2) }} spent</flux:text>
        @endif
    </div>

    {{-- Stacked bar -- KEEP AS-IS (custom progress bar) --}}
    <div class="flex h-6 rounded-full overflow-hidden gap-0.5 bg-zinc-100 dark:bg-zinc-800 mb-4">
        @foreach ($buckets as $bucket)
            @if ($bucket['pct'] > 0)
                <div class="{{ $bucket['color'] }} transition-all duration-500"
                     style="width: {{ $bucket['pct'] }}%"
                     title="{{ $bucket['label'] }}: {{ $bucket['pct'] }}%">
                </div>
            @endif
        @endforeach
    </div>

    {{-- Stat columns --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach ($buckets as $bucket)
            <div class="text-center">
                <flux:text size="xs" class="font-medium mb-1">{{ $bucket['label'] }}</flux:text>
                <flux:heading size="lg">{{ $bucket['pct'] }}%</flux:heading>
                <flux:text size="xs">target {{ $bucket['target'] }}%</flux:text>
                <flux:text size="xs">${{ number_format($bucket['spent'], 2) }}</flux:text>
                @if ($bucket['over'])
                    <flux:text size="xs" class="text-red-500 font-medium mt-0.5">over target</flux:text>
                @endif
            </div>
        @endforeach
    </div>
</flux:card>
```

- [ ] **Step 4: Migrate `flagged-transactions.blade.php` template**

Replace icons, headings, text, the category select, and badges:

```blade
<flux:card>
    @if ($flaggedTransactions->isNotEmpty())
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <flux:icon.alert-triangle class="text-amber-500" variant="mini" />
                <flux:heading size="sm">Needs Review</flux:heading>
            </div>
            <flux:badge color="amber" size="sm">{{ $flaggedTransactions->count() }}</flux:badge>
        </div>

        <div class="space-y-3">
            @foreach ($flaggedTransactions as $transaction)
                <div class="flex items-center gap-3 py-2 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium truncate">
                            {{ $transaction->merchant_name ?? $transaction->description }}
                        </flux:text>
                        <flux:text size="xs">
                            {{ $transaction->date->format('M j') }}
                            &middot; {{ $transaction->amount < 0 ? '-' : '+' }}${{ number_format(abs($transaction->amount), 2) }}
                        </flux:text>
                    </div>
                    <flux:select
                        wire:change="assignCategory({{ $transaction->id }}, $event.target.value)"
                        size="sm"
                        class="flex-shrink-0"
                    >
                        <flux:select.option value="">Assign category</flux:select.option>
                        @foreach ($categories as $category)
                            <flux:select.option
                                value="{{ $category->id }}"
                                :selected="$transaction->category_id === $category->id"
                            >{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex items-center gap-3">
            <flux:icon.check-circle class="size-8 text-green-500 dark:text-green-400 flex-shrink-0" />
            <div>
                <flux:heading size="sm">All Clear</flux:heading>
                <flux:text size="xs">No transactions need review</flux:text>
            </div>
        </div>
    @endif
</flux:card>
```

- [ ] **Step 5: Migrate `goals-summary.blade.php` template**

Replace icons, headings, text, and links. Keep the custom progress bars:

```blade
<flux:card>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon.target class="text-zinc-400 dark:text-zinc-500" variant="mini" />
            <flux:heading size="sm">Goals</flux:heading>
        </div>
        <flux:link href="/goals" wire:navigate size="sm">View all</flux:link>
    </div>

    @if ($goals->isEmpty())
        <div class="text-center py-4">
            <flux:text size="sm">No goals set</flux:text>
            <flux:link href="/goals" wire:navigate size="sm" class="mt-1 inline-block">Add a goal</flux:link>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($goals as $goal)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <flux:text size="xs" class="font-medium truncate mr-2">{{ $goal->name }}</flux:text>
                        <flux:text size="xs">{{ $goal->progressPercent() }}%</flux:text>
                    </div>
                    {{-- KEEP: custom progress bar --}}
                    <div class="h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                        <div class="h-1.5 rounded-full bg-blue-500 transition-all"
                            style="width: {{ min(100, $goal->progressPercent()) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totalGoals > 3)
            <flux:text size="xs" class="mt-3">and {{ $totalGoals - 3 }} more</flux:text>
        @endif
    @endif
</flux:card>
```

- [ ] **Step 6: Migrate `spending-chart.blade.php` template**

Replace card wrapper, headings, and buttons. Keep ApexCharts logic as-is:

```blade
<flux:card>
    <div class="flex items-center justify-between mb-1">
        <flux:heading size="sm">Spending Trend</flux:heading>
        <div class="flex gap-1">
            <flux:button
                wire:click="setDays(7)"
                size="xs"
                :variant="$days === 7 ? 'primary' : 'subtle'"
            >7d</flux:button>
            <flux:button
                wire:click="setDays(30)"
                size="xs"
                :variant="$days === 30 ? 'primary' : 'subtle'"
            >30d</flux:button>
        </div>
    </div>

    {{-- KEEP: ApexCharts rendering --}}
    <div wire:key="chart-{{ $days }}" x-data x-init="
        new ApexCharts($refs.chart, {
            chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
            series: [{ name: 'Spent', data: @js($chartValues) }],
            xaxis: { categories: @js($chartLabels) },
            colors: ['#3b82f6'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            grid: { borderColor: document.documentElement.classList.contains('dark') ? '#27272a' : '#e4e4e7' },
            theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
            dataLabels: { enabled: false },
        }).render()
    " class="mt-4">
        <div x-ref="chart"></div>
    </div>
</flux:card>
```

- [ ] **Step 7: Migrate `summary-snippet.blade.php` template**

```blade
<flux:card>
    <div class="flex items-center gap-2 mb-4">
        <flux:icon.sparkles class="text-zinc-400 dark:text-zinc-500" variant="mini" />
        <flux:heading size="sm">Today's Summary</flux:heading>
    </div>

    @if ($latestSummary)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:text size="sm">Total Spent</flux:text>
                <flux:heading size="lg">${{ number_format($latestSummary->total_spent, 2) }}</flux:heading>
            </div>

            @if ($latestSummary->ai_analysis)
                <flux:text size="sm" class="leading-relaxed">
                    {{ Str::limit($latestSummary->ai_analysis, 200) }}
                </flux:text>
            @endif

            <flux:text size="xs">{{ $latestSummary->period_start->format('M j, Y') }}</flux:text>
        </div>
    @else
        <div class="text-center py-4">
            <flux:icon.file-text class="size-8 text-zinc-300 dark:text-zinc-700 mx-auto mb-2" />
            <flux:text size="sm">No summaries yet</flux:text>
        </div>
    @endif
</flux:card>
```

- [ ] **Step 8: Verify dashboard renders correctly**

Open http://localhost:8001/dashboard. Verify all 6 widgets render with Flux components, charts still work, progress bars still show.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: migrate dashboard page and all widgets to Flux UI"
```

---

### Task 4: Migrate Transactions Page

**Files:**
- Modify: `resources/views/pages/transactions.blade.php`
- Modify: `resources/views/livewire/transactions/transaction-list.blade.php` (template only)

- [ ] **Step 1: Migrate the page heading in `pages/transactions.blade.php`**

Replace `<h1>` and `<p>` with `flux:heading` and `flux:text`.

- [ ] **Step 2: Migrate the transaction-list template**

Replace the filter section inputs/selects with `flux:input`, `flux:select`, `flux:checkbox`. Replace the bulk action bar with `flux:button` and `flux:select`. Replace the `<table>` with `flux:table` components. Replace badges with `flux:badge`. Replace the inline category `<select>` with `flux:select`. Keep the PHP class completely untouched.

Key mappings:
- Search input -> `<flux:input wire:model.live.debounce.300ms="search" placeholder="Search transactions..." icon="magnifying-glass" size="sm" />`
- Account filter -> `<flux:select wire:model.live="accountFilter" size="sm">`
- Category filter -> `<flux:select wire:model.live="categoryFilter" size="sm">`
- Needs Review checkbox -> `<flux:checkbox wire:model.live="reviewFilter" label="Needs Review" />`
- Table -> `<flux:table>` with `<flux:table.columns>`, `<flux:table.column sortable>`, `<flux:table.rows>`, `<flux:table.row>`, `<flux:table.cell>`
- Status badges -> `<flux:badge color="yellow">Review</flux:badge>` and `<flux:badge color="green">OK</flux:badge>`
- Bulk categorize button -> `<flux:button variant="primary" size="sm">`
- Clear button -> `<flux:button variant="ghost" size="sm">`

- [ ] **Step 3: Verify transactions page**

Open http://localhost:8001/transactions. Verify filters, sorting, bulk selection, inline category changes, and pagination all work.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: migrate transactions page to Flux UI table and form components"
```

---

### Task 5: Migrate Budgets Page

**Files:**
- Modify: `resources/views/pages/budgets.blade.php`
- Modify: `resources/views/livewire/budgets/budget-manager.blade.php` (template only)

- [ ] **Step 1: Migrate the budget-manager template**

Replace headings, text, buttons, inputs, selects, and icons. Keep the custom progress bars. Key mappings:
- Month navigation buttons -> `<flux:button variant="subtle" square icon="chevron-left" />`
- Heading -> `<flux:heading size="xl">`
- Bucket section headings -> `<flux:heading size="sm">`
- Budget amounts text -> `<flux:text>`
- Icons -> `<flux:icon.*>`
- Edit/Delete buttons -> `<flux:button variant="subtle" size="sm" icon="pencil" />`
- Add/Edit form -> `<flux:card>` with `<flux:input>`, `<flux:select>`, `<flux:button>`
- Budget cards -> `<flux:card>`
- Separator between budgets -> implicit via card structure

- [ ] **Step 2: Verify budgets page**

Test month navigation, budget display, add/edit form, and progress bars.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: migrate budgets page to Flux UI components"
```

---

### Task 6: Migrate Categories Page

**Files:**
- Modify: `resources/views/pages/categories.blade.php`
- Modify: `resources/views/livewire/categories/category-manager.blade.php` (template only)

- [ ] **Step 1: Migrate the category-manager template**

Key mappings:
- Categories table -> `<flux:table>` with proper columns
- Lookback buttons -> `<flux:button>` group
- Add Category button -> `<flux:button variant="primary" icon="plus">`
- Bucket badges -> `<flux:badge>` with appropriate colors
- Essential badges -> `<flux:badge color="amber">`
- System badges -> `<flux:badge color="zinc">`
- Trend icons -> `<flux:icon.trending-up>`, `<flux:icon.trending-down>`, `<flux:icon.minus>`
- Edit/Delete buttons -> `<flux:button variant="subtle" size="sm">`
- The category modal -> `<flux:modal name="category-editor">` with `<flux:input>`, `<flux:select>`, `<flux:switch>`, `<flux:button>`
- Dynamic icons in table -> `<flux:icon :icon="$category->icon ?? 'tag'" variant="mini" />`

- [ ] **Step 2: Update PHP class to use Flux modal API**

Replace `$showModal` boolean with Flux's modal name system. Use `Flux::modal('category-editor')->show()` to open and `Flux::modal('category-editor')->close()` to close.

- [ ] **Step 3: Verify categories page**

Test table display, edit modal, create modal, icon picker, bucket selector, essential toggle.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: migrate categories page to Flux UI with modal"
```

---

### Task 7: Migrate Accounts Page

**Files:**
- Modify: `resources/views/pages/accounts.blade.php`
- Modify: `resources/views/livewire/accounts/account-list.blade.php` (template only)
- Modify: `resources/views/livewire/accounts/add-account.blade.php` (template only)
- Modify: `resources/views/livewire/accounts/csv-import.blade.php` (template only)

- [ ] **Step 1: Migrate account-list template**

Replace cards with `<flux:card>`, icons with `<flux:icon.*>`, text/headings with `<flux:heading>`/`<flux:text>`, links with `<flux:link>`. Keep ApexCharts sparklines as-is.

- [ ] **Step 2: Migrate add-account template**

Replace form with `<flux:card>`, inputs with `<flux:input label="...">`, select with `<flux:select>`, buttons with `<flux:button>`.

- [ ] **Step 3: Migrate csv-import template**

Replace file input with `<flux:input type="file">`, select with `<flux:select>`, buttons with `<flux:button>`, status messages with `<flux:callout>`.

- [ ] **Step 4: Verify accounts page**

Test account display, manual account creation, and CSV import flow.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: migrate accounts page to Flux UI components"
```

---

### Task 8: Migrate Settings Page

**Files:**
- Modify: `resources/views/pages/settings.blade.php`
- Modify: `resources/views/livewire/settings/budget-ratios.blade.php` (template only)
- Modify: `resources/views/livewire/settings/email-recipients.blade.php` (template only)
- Modify: `resources/views/livewire/settings/income-sources.blade.php` (template only)
- Modify: `resources/views/livewire/settings/sync-schedule.blade.php` (template only)

- [ ] **Step 1: Migrate settings page heading**

Replace with `<flux:heading>` and `<flux:text>`.

- [ ] **Step 2: Migrate budget-ratios template**

Replace inputs with `<flux:input type="number" label="...">`, validation indicator with `<flux:badge>` or `<flux:icon.*>`, save button with `<flux:button>`. Wrap in `<flux:card>`.

- [ ] **Step 3: Migrate email-recipients template**

Replace input with `<flux:input>`, add button with `<flux:button icon="plus">`, remove buttons with `<flux:button variant="subtle" icon="trash-2">`. Wrap in `<flux:card>`.

- [ ] **Step 4: Migrate income-sources template**

Replace form inputs with `<flux:input>`, `<flux:select>`, date picker with `<flux:input type="date">`, buttons with `<flux:button>`. Wrap in `<flux:card>`.

- [ ] **Step 5: Migrate sync-schedule template**

Replace selects with `<flux:select>`, slider with `<flux:input type="range">`, button with `<flux:button>`. Wrap in `<flux:card>`.

- [ ] **Step 6: Verify settings page**

Test all four settings panels: budget ratios, email recipients, income sources, sync schedule.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: migrate settings page components to Flux UI"
```

---

### Task 9: Migrate Goals, Summaries, Chat, and Profile Pages

**Files:**
- Modify: `resources/views/pages/goals.blade.php`
- Modify: `resources/views/livewire/goals/goal-manager.blade.php` (template only)
- Modify: `resources/views/pages/summaries.blade.php`
- Modify: `resources/views/livewire/summaries/summary-archive.blade.php` (template only)
- Modify: `resources/views/pages/chat.blade.php`
- Modify: `resources/views/livewire/chat/chat-page.blade.php` (template only)
- Modify: `resources/views/pages/profile.blade.php`
- Modify: `resources/views/livewire/profile/financial-profile.blade.php` (template only)

- [ ] **Step 1: Migrate goal-manager template**

Replace summary stat cards with `<flux:card>`, form inputs with `<flux:input>`, `<flux:select>`, `<flux:textarea>`, buttons with `<flux:button>`, priority badges with `<flux:badge>`, icons with `<flux:icon.*>`. Keep custom progress bars.

- [ ] **Step 2: Migrate summary-archive template**

Replace tab buttons with `<flux:button>` group (or Flux tabs if using Pro), summary cards with `<flux:card>`, text/headings with `<flux:heading>`/`<flux:text>`, badges with `<flux:badge>`. Keep custom 50/30/20 bar.

- [ ] **Step 3: Migrate chat-page template**

Replace conversation sidebar links, input form, buttons with Flux components. Keep the custom bubble CSS classes (`bubble-user`, `bubble-assistant`) and chat animations. Replace the delete confirmation with `<flux:modal>`.

- [ ] **Step 4: Migrate financial-profile template**

Replace cards with `<flux:card>`, headings with `<flux:heading>`, text with `<flux:text>`, icons with `<flux:icon.*>`, links with `<flux:link>`. Keep custom progress bars.

- [ ] **Step 5: Verify all four pages**

Test goals (add/edit/complete/delete), summaries (tab switching, pagination), chat (send message, conversation management), profile (data display).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: migrate goals, summaries, chat, and profile pages to Flux UI"
```

---

### Task 10: Migrate Auth Views and Clean Up

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Modify: `resources/views/auth/forgot-password.blade.php`
- Modify: `resources/views/auth/reset-password.blade.php`
- Delete: `resources/views/components/danger-button.blade.php`
- Delete: `resources/views/components/dropdown.blade.php`
- Delete: `resources/views/components/dropdown-link.blade.php`
- Delete: `resources/views/components/input-error.blade.php`
- Delete: `resources/views/components/input-label.blade.php`
- Delete: `resources/views/components/modal.blade.php`
- Delete: `resources/views/components/nav-link.blade.php`
- Delete: `resources/views/components/primary-button.blade.php`
- Delete: `resources/views/components/responsive-nav-link.blade.php`
- Delete: `resources/views/components/secondary-button.blade.php`
- Delete: `resources/views/components/text-input.blade.php`

- [ ] **Step 1: Migrate `auth/login.blade.php`**

```blade
<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Log in to your account</flux:heading>
            <flux:text class="mt-2">Welcome back!</flux:text>
        </div>

        <flux:input label="Email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />

        <flux:field>
            <div class="mb-1 flex justify-between">
                <flux:label>Password</flux:label>
                @if (Route::has('password.request'))
                    <flux:link href="{{ route('password.request') }}" variant="subtle" size="sm">Forgot password?</flux:link>
                @endif
            </div>
            <flux:input type="password" name="password" required autocomplete="current-password" />
            <flux:error name="password" />
        </flux:field>

        <flux:checkbox name="remember" label="Remember me" />

        <flux:button variant="primary" type="submit" class="w-full">Log in</flux:button>
    </form>
</x-guest-layout>
```

- [ ] **Step 2: Migrate `auth/register.blade.php`**

```blade
<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Create your account</flux:heading>
            <flux:text class="mt-2">Get started with Better With 90.</flux:text>
        </div>

        <flux:input label="Name" name="name" :value="old('name')" required autofocus autocomplete="name" />
        <flux:input label="Email" type="email" name="email" :value="old('email')" required autocomplete="username" />
        <flux:input label="Password" type="password" name="password" required autocomplete="new-password" />
        <flux:input label="Confirm Password" type="password" name="password_confirmation" required autocomplete="new-password" />

        <div class="flex items-center justify-between">
            <flux:link href="{{ route('login') }}" variant="subtle" size="sm">Already have an account?</flux:link>
            <flux:button variant="primary" type="submit">Register</flux:button>
        </div>
    </form>
</x-guest-layout>
```

- [ ] **Step 3: Migrate `auth/forgot-password.blade.php`**

```blade
<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Forgot your password?</flux:heading>
            <flux:text class="mt-2">No problem. Enter your email and we'll send you a reset link.</flux:text>
        </div>

        <flux:input label="Email" type="email" name="email" :value="old('email')" required autofocus />

        <flux:button variant="primary" type="submit" class="w-full">Email Password Reset Link</flux:button>
    </form>
</x-guest-layout>
```

- [ ] **Step 4: Migrate `auth/reset-password.blade.php`**

```blade
<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Reset your password</flux:heading>
            <flux:text class="mt-2">Enter your new password below.</flux:text>
        </div>

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <flux:input label="Email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        <flux:input label="New Password" type="password" name="password" required autocomplete="new-password" />
        <flux:input label="Confirm Password" type="password" name="password_confirmation" required autocomplete="new-password" />

        <flux:button variant="primary" type="submit" class="w-full">Reset Password</flux:button>
    </form>
</x-guest-layout>
```

- [ ] **Step 5: Delete unused Breeze components**

```bash
rm resources/views/components/danger-button.blade.php
rm resources/views/components/dropdown.blade.php
rm resources/views/components/dropdown-link.blade.php
rm resources/views/components/input-error.blade.php
rm resources/views/components/input-label.blade.php
rm resources/views/components/modal.blade.php
rm resources/views/components/nav-link.blade.php
rm resources/views/components/primary-button.blade.php
rm resources/views/components/responsive-nav-link.blade.php
rm resources/views/components/secondary-button.blade.php
rm resources/views/components/text-input.blade.php
```

Keep `application-logo.blade.php` and `auth-session-status.blade.php` (still referenced).

- [ ] **Step 6: Remove the `mallardduck/blade-lucide-icons` package**

Since all icons now use `flux:icon.*`, the standalone Lucide package is no longer needed:

```bash
composer remove mallardduck/blade-lucide-icons
```

- [ ] **Step 7: Full application smoke test**

Visit every page and verify:
- Dashboard: all widgets render
- Transactions: table, filters, sorting, inline category change
- Budgets: month nav, progress bars, add/edit form
- Categories: table, modal create/edit
- Accounts: account cards, add form, CSV import
- Settings: all four panels
- Goals: add/edit/complete
- Summaries: tab switching
- Chat: send messages
- Profile: data display
- Login/Register/Forgot Password: all forms work

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: migrate auth views to Flux UI, remove unused Breeze components"
```
