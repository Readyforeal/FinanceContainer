# StewardAI — Personal Finance Tracking & Advisory Platform

## Overview

A containerized personal finance application that syncs bank transactions via Plaid, auto-categorizes spending with a local LLM (Ollama), enforces the 50-30-20 budgeting rule, detects spending habits, optimizes bill payment timing, and provides financial advice from a biblical stewardship perspective. Built for a single household (two users), deployed on a Linux desktop with an RTX 3070 GPU.

### Core Problem

Bi-weekly income of ~$6,200/month ($2,400 main job + $700 church, both bi-weekly) that "vanishes" — low account balances, volatility, inability to plan for large purchases, and no visibility into where money goes. The household owns a home, has two kids, needs to save for cars (cash purchases), renovations, repairs, and maintenance while still enjoying hobbies and life.

### Success Criteria

- Transactions sync daily and are auto-categorized with high accuracy
- Clear visibility into 50-30-20 budget adherence at all times
- AI challenges bad spending habits and recommends budget adjustments
- Bill payment timing is optimized to maintain stable cash flow across the month
- Purchase planning gives specific, actionable timelines for large buys
- Daily/weekly/monthly summaries keep both spouses informed without effort
- System grows in knowledge of the household's patterns, goals, and habits over time

---

## Architecture

### Docker Compose Stack

Six services in one `docker-compose.yml`:

| Service | Image/Base | Port | Purpose |
|---------|-----------|------|---------|
| **nginx** | nginx:alpine | 80 | Reverse proxy → PHP-FPM |
| **app** | PHP 8.3-FPM + Laravel | 9000 (internal) | Application server: Livewire frontend, API, scheduler |
| **ollama** | ollama/ollama | 11434 | Local LLM. GPU passthrough on Linux, CPU fallback on Mac |
| **postgres** | postgres:16 | 5432 | All persistent data |
| **redis** | redis:alpine | 6379 | Queue backend, cache, sessions |
| **worker** | Same as app | — | `php artisan queue:work` — dedicated queue consumer |

**GPU Configuration:** Ollama container uses `deploy.resources.reservations.devices` for NVIDIA GPU passthrough. On the Mac development environment, it runs on CPU (slower but functional for testing). On the Linux production box with the RTX 3070, it picks up the GPU automatically.

**Ollama Model:** Llama 3.1 70B Q4 quantized. Fits in 8GB VRAM with CPU offloading on the 3070. Prioritizes advice quality over response speed — the user accepts slower responses for better financial reasoning.

### Data Flow

**Automated (daily, configurable schedule, default 4 AM):**

```
Scheduler triggers PlaidSyncJob
  → Fetch new transactions from Plaid (checking + savings)
  → Store in DB
  → Dispatch CategorizationJob
    → Ollama categorizes each new transaction
    → Flag if confidence < configurable threshold (default 0.9)
  → Dispatch BudgetCheckJob
    → Compare current month spending against budgets and 50-30-20 targets
  → Dispatch HealthCheckJob
    → Analyze trends, detect habits, evaluate goal progress
    → Generate budget adjustment recommendations
  → Dispatch PaymentOptimizerJob
    → Analyze essential category patterns vs pay schedule vs balances
    → Recommend bill payment timing for stable cash flow
    → Generate payment reminder emails
  → Dispatch SummaryJob
    → Compile daily Summary model
    → Email to household
    → Weekly/monthly summaries on their respective schedules
```

**On-demand:**

```
User opens dashboard → Livewire renders data from DB
User opens chat → Message + financial context sent to Ollama → Response streamed back
```

---

## Data Model

### User

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | |
| email | string | unique |
| password | string | hashed |
| role | enum | `admin`, `member` |
| timestamps | | |

Relationships: hasMany ChatConversation

### PlaidConnection

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| access_token | string | encrypted at rest |
| item_id | string | Plaid item identifier |
| institution_name | string | Display name of the bank |
| cursor | string | nullable, for sync pagination |
| status | enum | `active`, `error`, `needs_reauth` |
| timestamps | | |

Relationships: hasMany Account

### Account

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| plaid_connection_id | bigint | FK |
| plaid_account_id | string | Plaid's account identifier |
| name | string | "Checking", "Savings" |
| type | enum | `checking`, `savings` |
| current_balance | decimal(12,2) | |
| available_balance | decimal(12,2) | |
| last_synced_at | timestamp | nullable |
| timestamps | | |

Relationships: belongsTo PlaidConnection, hasMany Transaction

### Transaction

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| account_id | bigint | FK |
| plaid_transaction_id | string | unique, Plaid's identifier |
| amount | decimal(10,2) | |
| date | date | |
| merchant_name | string | nullable |
| description | string | |
| plaid_category | string | nullable, raw from Plaid |
| category_id | bigint | FK, nullable (null = uncategorized) |
| categorization_confidence | decimal(3,2) | 0.00–1.00 |
| needs_review | boolean | default false, true when confidence < threshold |
| is_recurring | boolean | default false |
| budget_bucket | enum | `needs`, `wants`, `savings`, nullable |
| timestamps | | |

Relationships: belongsTo Account, belongsTo Category

### Category

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | "Groceries", "Electric", etc. |
| icon | string | Lucide icon name |
| default_bucket | enum | `needs`, `wants`, `savings` |
| is_essential | boolean | default false — marks survival-level expenses |
| is_system | boolean | default false — seeded vs user-created |
| timestamps | | |

Relationships: hasMany Transaction, hasMany Budget

Computed (not stored): average spend over configurable lookback period, trend direction.

### Budget

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| category_id | bigint | FK |
| month | string | YYYY-MM format |
| budgeted_amount | decimal(10,2) | |
| bucket | enum | `needs`, `wants`, `savings` |
| timestamps | | |

Relationships: belongsTo Category

Display: Shows budgeted amount as a percentage of total monthly income. Warns when total budgeted across all categories exceeds total income.

### IncomeSource

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | "Main Job", "Church" |
| amount | decimal(10,2) | Per-period amount |
| frequency | enum | `weekly`, `biweekly`, `monthly` |
| next_pay_date | date | |
| is_active | boolean | default true |
| timestamps | | |

Used to calculate total monthly income ceiling for budget percentages and to inform Ollama's payment optimization (knows when money arrives).

### Summary

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| type | enum | `daily`, `weekly`, `monthly` |
| period_start | date | |
| period_end | date | |
| total_income | decimal(10,2) | |
| total_spent | decimal(10,2) | |
| needs_spent | decimal(10,2) | |
| wants_spent | decimal(10,2) | |
| savings_spent | decimal(10,2) | |
| ai_analysis | text | Ollama's financial analysis |
| ai_advice | text | Biblical perspective advice |
| habit_flags | json | Detected patterns and warnings |
| timestamps | | |

### ChatConversation

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK |
| title | string | |
| timestamps | | |

Relationships: belongsTo User, hasMany ChatMessage

### ChatMessage

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| conversation_id | bigint | FK |
| user_id | bigint | FK, nullable (null for assistant messages) |
| role | enum | `user`, `assistant` |
| content | text | |
| timestamps | | |

Relationships: belongsTo ChatConversation, belongsTo User

### AppSetting

| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| key | string | unique |
| value | json | |
| timestamps | | |

Keys include: `sync_schedule` (cron expression, default `0 4 * * *`), `categorization_confidence_threshold` (default `0.9`), `budget_ratios` (default `{"needs": 50, "wants": 30, "savings": 20}`), `email_recipients` (array of emails), `ollama_model` (default `llama3.1:70b-instruct-q4_K_M`).

---

## Ollama Integration

### AI Responsibilities

1. **Transaction Categorization** — Assign each new transaction a category from the existing category list + confidence score + budget bucket. If confidence < configurable threshold, set `needs_review = true`.

2. **Budget Analysis** — Compare current spending against budgets and 50-30-20 targets. For discretionary categories trending over budget: challenge the behavior, connect overspending to goal impact. For essential categories trending above budget: recommend increasing the budget and identify where to offset.

3. **Financial Health Check** — Detect spending habits and patterns over time. Evaluate goal achievability given current trajectory. Provide advice on being better stewards from a biblical perspective.

4. **Payment Optimization** — Analyze essential category transaction patterns (average amount, typical billing date, flexibility) against the household's pay schedule and current balances. Recommend a payment calendar that spreads bills across pay periods to maintain stable cash flow. Generate email reminders when it's time to pay each bill.

5. **Purchase Planning** — Via chat, help plan large or discretionary purchases. Answer "can I afford this now?" with honest math. If not now, propose a savings plan with a specific target purchase date that doesn't compromise other goals.

6. **Summary Generation** — Compile daily/weekly/monthly reports with totals, breakdowns, analysis, advice, and habit flags.

### Context Strategy

**System Prompt (always present in every Ollama interaction):**

- Household financial profile and goals
- All income sources with amounts, frequencies, and pay dates
- 50-30-20 rule targets (or custom ratios from AppSetting)
- Complete category list with essential flags and default buckets
- Current budget allocations per category
- Known goals (cars, renovations, etc.)
- Behavioral rules:
  - Be critical of bad spending habits — don't normalize discretionary overspending
  - Challenge wants-category increases, recommend pulling back
  - Accept essential-category increases as reality, find offsets elsewhere
  - Connect spending decisions to concrete goal impact
  - Provide biblical stewardship perspective
  - Maximize fun and hobbies within responsible boundaries
  - When planning purchases, give specific dates based on pay schedule and cash flow

**Dynamic Context (injected per job or chat message):**

- Current checking and savings balances
- Recent transactions (last 30 days)
- Category average spend and trend data
- Current month spending vs budget per category
- Upcoming essential bill patterns and pay dates
- Previous summary highlights
- Flagged transactions needing review
- Goal progress and savings trajectory

### Communication with Ollama

Laravel communicates with Ollama via its HTTP API (`http://ollama:11434/api/chat` and `/api/generate`). A dedicated Laravel service class (`OllamaService`) handles:

- Building the system prompt from current DB state
- Assembling dynamic context for each job type
- Sending requests and parsing responses
- Streaming responses for the chat interface (via Livewire)
- Structured output parsing for categorization (JSON mode)

Ollama jobs run on a dedicated Redis queue (`ai`) separate from the default queue, so AI processing (which is slow on the 70B model) doesn't block Plaid sync, emails, or other fast jobs.

---

## Frontend

### Technology

- **Laravel Livewire 3** — Reactive components without JavaScript framework overhead
- **Blade templates** — Server-rendered views
- **ApexCharts** — All data visualization (MIT licensed, free, full chart library)
- **Lucide Icons** — Clean, consistent iconography throughout
- **Tailwind CSS** — Utility-first styling

### Design Principles

- Clean, modest, modern, and elegant
- Thoughtful weight and whitespace balance
- Information-dense but not cluttered — a professional financial tool
- Absolutely no emojis anywhere in the UI
- Lucide icons exclusively for all iconography
- Dark theme (matches the mockups — financial data reads well on dark backgrounds)

### Layout

Sidebar navigation (fixed left) with main content area. Sidebar contains:

- Dashboard (home)
- Transactions
- Budgets
- Categories
- Accounts
- Summaries
- Chat
- Settings (bottom of sidebar, separated)

### Pages

**Dashboard**
- Account balance cards: checking, savings, next payday countdown
- 50-30-20 progress bar with bucket percentages and warnings when off-target
- Spending trend chart (7-day default, toggleable to 30-day)
- Today's AI summary snippet
- Flagged transactions needing review with inline categorization

**Transactions**
- Filterable, searchable, sortable table
- Filters: account, category, date range, bucket, review status, recurring
- Inline category assignment for flagged items (dropdown)
- Bulk select and categorize

**Budgets**
- Category budget list: budgeted amount, % of total monthly income, spent this month, remaining
- 50-30-20 bucket totals with visual progress bars
- Warning when total budgeted exceeds total income
- Ollama's budget adjustment recommendations displayed inline

**Categories**
- CRUD for categories: name, Lucide icon picker, default bucket, essential toggle
- Average spend display with configurable lookback (3/6/12 months, all time)
- Trend indicator per category

**Accounts**
- Checking and savings cards with balance history chart
- Plaid connection status and last sync timestamp
- Plaid Link widget for connecting or reconnecting bank accounts

**Summaries**
- Tab interface: Daily / Weekly / Monthly
- Each summary displays: period totals, 50-30-20 breakdown, AI analysis, biblical advice, habit flags
- Historical archive with scrollable list

**Chat**
- Conversation list (left panel within the page)
- Chat interface: message input, streamed AI responses
- New conversation button
- Financial context injected automatically — user just asks naturally

**Settings**
- Income sources: add/edit/remove with name, amount, frequency, next pay date
- Sync schedule: time picker (stored as cron in AppSetting)
- Categorization confidence threshold: slider (0.5–1.0, default 0.9)
- Budget ratio targets: adjustable (default 50/30/20, must sum to 100)
- Email recipients: manage list
- Plaid connection management: status, reconnect

---

## Notifications

- **In-app:** Summaries stored as Summary models, displayed on the Summaries page and snippeted on the Dashboard
- **Email:** Daily/weekly/monthly summaries emailed to configured recipients. Bill payment reminders emailed when Ollama's optimizer says it's time to pay. Laravel's built-in mail system with SMTP configuration.

---

## Implementation Phases

### Phase 1: Foundation

Docker Compose setup, Laravel application scaffolding, database schema and migrations, Plaid integration (Link widget + transaction sync), and the basic transaction storage pipeline.

**Deliverables:**
- `docker-compose.yml` with all six services
- Dockerfiles for app/worker (PHP 8.3-FPM, Composer, Node for asset compilation)
- Nginx configuration
- Laravel project with Livewire, Tailwind CSS, ApexCharts installed
- All database migrations
- Eloquent models with relationships
- Category seeder with common categories (groceries, gas, electric, mortgage, dining out, etc.)
- AppSetting seeder with defaults
- User seeder (admin + member accounts)
- Plaid Link Livewire component (onboarding flow)
- Plaid service class (link token creation, token exchange, transaction sync)
- PlaidSyncJob (queued, fetches transactions and stores them)
- Scheduler entry for PlaidSyncJob based on AppSetting sync_schedule
- Account and transaction list views (basic Livewire components)
- Authentication (Laravel Breeze or Fortify, simple login)
- Settings page for income sources and sync schedule

### Phase 2: AI Engine

Ollama integration, transaction auto-categorization, financial health analysis, budget recommendations, payment optimization, and the advisory chat interface.

**Deliverables:**
- OllamaService class (system prompt builder, context assembler, HTTP client, JSON parsing, streaming)
- CategorizationJob — categorizes new transactions, flags uncertain ones
- BudgetCheckJob — 50-30-20 analysis, per-category budget vs actual
- HealthCheckJob — habit detection, trend analysis, goal evaluation, budget adjustment recommendations
- PaymentOptimizerJob — essential bill timing analysis, payment calendar, reminder email generation
- SummaryJob — compiles Summary models (daily/weekly/monthly)
- Job chaining (sync → categorize → budget → health → optimizer → summary)
- Chat Livewire component with streaming responses
- ChatConversation and ChatMessage persistence
- Transaction review UI (inline categorization for flagged items)
- Confidence threshold setting in Settings page
- Dedicated `ai` queue configuration in Redis

### Phase 3: Dashboard & Reporting

The full frontend experience — dashboard home page, all data visualization, summary views, budget management UI, and email delivery.

**Deliverables:**
- Dashboard home page with all widgets (balances, 50-30-20 bar, spending trend chart, summary snippet, flagged transactions)
- Transactions page (filterable table, bulk categorization)
- Budgets page (budget amounts, % of income, progress bars, AI recommendations)
- Categories page (CRUD, average spend, trends, essential toggle, Lucide icon picker)
- Accounts page (balance history charts, Plaid status)
- Summaries page (daily/weekly/monthly tabs, historical archive)
- ApexCharts integration (spending trends, budget progress, balance history, category breakdowns)
- Email templates for daily/weekly/monthly summaries
- Email templates for bill payment reminders
- Responsive layout refinement

### Phase 4: Growth & Goals

Goal tracking, large purchase planning, habit detection over time, and the system's growing knowledge of the household.

**Deliverables:**
- Goal model (name, target amount, target date, priority, linked category/bucket)
- Goal tracking UI (progress visualization, timeline, impact of current spending on achievability)
- Goal integration into Ollama context (chat and automated analysis reference goals)
- Habit detection refinement (Ollama identifies recurring patterns over longer time horizons — 3/6/12 months)
- Purchase planning chat enhancement (Ollama proposes specific savings plans with purchase date targets based on goals, bills, and income)
- Ollama memory/context growth — system prompt evolves as more data accumulates, summaries reference prior summaries for trend awareness
- Household financial profile page (consolidated view of income, expenses, goals, net trajectory)
- Annual projections and "what if" scenarios via chat

---

## Deployment Notes

**Development (MacBook Air):**
- All containers run via Docker Compose
- Ollama runs on CPU — expect slow AI responses, functional for testing
- Plaid sandbox mode for test transactions

**Production (Linux Desktop, RTX 3070):**
- Same Docker Compose file, GPU passthrough enabled for Ollama
- Plaid production mode with real bank credentials
- SMTP configured for email delivery
- Data persisted via Docker volumes

**Portability:** The entire application is one `docker compose up -d` command on any Docker-capable machine. Moving from Mac to Linux requires only copying the compose file and `.env`, then running compose up. Database can be migrated via `pg_dump`/`pg_restore` or starting fresh and re-syncing from Plaid.
