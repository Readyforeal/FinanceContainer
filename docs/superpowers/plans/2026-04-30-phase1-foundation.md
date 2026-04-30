# Phase 1: Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the full Docker Compose stack with a Laravel application that connects to Plaid, syncs transactions daily, and provides basic account/transaction views with authentication.

**Architecture:** Six-service Docker Compose stack (Nginx, PHP-FPM Laravel app, PostgreSQL 16, Redis, Ollama, queue worker). Laravel handles all application logic — Livewire for frontend reactivity, queued jobs for Plaid sync, scheduler for daily automation. Plaid Link widget for bank onboarding, cursor-based transaction sync.

**Tech Stack:** Laravel 13, Livewire 3, Tailwind CSS, PHP 8.3, PostgreSQL 16, Redis, Nginx, Docker Compose, Plaid (tomorrow-ideas/plaid-sdk-php), Lucide icons (mallardduck/blade-lucide-icons), ApexCharts (npm)

**Spec:** `docs/superpowers/specs/2026-04-30-steward-ai-design.md`

---

## File Structure

```
steward/                              # Laravel project root (created inside FinanceContainer)
├── app/
│   ├── Enums/
│   │   ├── AccountType.php           # checking, savings
│   │   ├── BudgetBucket.php          # needs, wants, savings
│   │   ├── PlaidConnectionStatus.php # active, error, needs_reauth
│   │   └── UserRole.php              # admin, member
│   ├── Http/
│   │   └── Middleware/
│   │       └── AdminOnly.php         # Gate for admin-only routes
│   ├── Jobs/
│   │   └── PlaidSyncJob.php          # Queued job: fetch + store transactions
│   ├── Livewire/
│   │   ├── Accounts/
│   │   │   └── AccountList.php       # Account list with balances
│   │   ├── Layout/
│   │   │   └── Sidebar.php           # Sidebar navigation component
│   │   ├── Plaid/
│   │   │   └── PlaidLink.php         # Plaid Link widget handler
│   │   ├── Settings/
│   │   │   ├── IncomeSources.php     # CRUD income sources
│   │   │   └── SyncSchedule.php      # Sync schedule config
│   │   └── Transactions/
│   │       └── TransactionList.php   # Transaction table with filters
│   ├── Models/
│   │   ├── Account.php
│   │   ├── AppSetting.php
│   │   ├── Budget.php
│   │   ├── Category.php
│   │   ├── ChatConversation.php
│   │   ├── ChatMessage.php
│   │   ├── IncomeSource.php
│   │   ├── PlaidConnection.php
│   │   ├── Summary.php
│   │   ├── Transaction.php
│   │   └── User.php                  # Modify existing
│   └── Services/
│       └── PlaidService.php          # Plaid API wrapper
├── database/
│   ├── migrations/
│   │   ├── xxxx_add_role_to_users_table.php
│   │   ├── xxxx_create_plaid_connections_table.php
│   │   ├── xxxx_create_accounts_table.php
│   │   ├── xxxx_create_categories_table.php
│   │   ├── xxxx_create_transactions_table.php
│   │   ├── xxxx_create_budgets_table.php
│   │   ├── xxxx_create_income_sources_table.php
│   │   ├── xxxx_create_summaries_table.php
│   │   ├── xxxx_create_chat_conversations_table.php
│   │   ├── xxxx_create_chat_messages_table.php
│   │   └── xxxx_create_app_settings_table.php
│   └── seeders/
│       ├── CategorySeeder.php
│       ├── AppSettingSeeder.php
│       └── UserSeeder.php
├── resources/views/
│   ├── components/
│   │   └── layouts/
│   │       └── app.blade.php         # Main app layout with sidebar
│   └── livewire/
│       ├── accounts/
│       │   └── account-list.blade.php
│       ├── layout/
│       │   └── sidebar.blade.php
│       ├── plaid/
│       │   └── plaid-link.blade.php
│       ├── settings/
│       │   ├── income-sources.blade.php
│       │   └── sync-schedule.blade.php
│       └── transactions/
│           └── transaction-list.blade.php
├── routes/
│   └── web.php                       # Modify existing
├── tests/
│   ├── Feature/
│   │   ├── Livewire/
│   │   │   ├── AccountListTest.php
│   │   │   ├── PlaidLinkTest.php
│   │   │   ├── TransactionListTest.php
│   │   │   ├── IncomeSourcesTest.php
│   │   │   └── SyncScheduleTest.php
│   │   └── Jobs/
│   │       └── PlaidSyncJobTest.php
│   └── Unit/
│       ├── Models/
│       │   ├── AccountTest.php
│       │   ├── CategoryTest.php
│       │   ├── PlaidConnectionTest.php
│       │   └── TransactionTest.php
│       └── Services/
│           └── PlaidServiceTest.php
docker/
├── app/
│   └── Dockerfile                    # PHP 8.3-FPM + extensions + Composer + Node
├── nginx/
│   └── default.conf                  # Nginx → PHP-FPM proxy
└── worker/
    └── entrypoint.sh                 # Queue worker startup script
docker-compose.yml
.env.example
```

---

### Task 1: Docker Infrastructure

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/app/Dockerfile`
- Create: `docker/nginx/default.conf`
- Create: `docker/worker/entrypoint.sh`
- Create: `.env.example`

- [ ] **Step 1: Create the app Dockerfile**

```dockerfile
# docker/app/Dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    git \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl

COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY steward/ .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
```

- [ ] **Step 2: Create the Nginx config**

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 3: Create the worker entrypoint**

```bash
#!/bin/sh
# docker/worker/entrypoint.sh

# Wait for the app to be ready
sleep 5

# Run the queue worker for both default and ai queues
php /var/www/html/artisan queue:work redis --queue=default,ai --sleep=3 --tries=3 --max-time=3600
```

- [ ] **Step 4: Create .env.example**

```env
# .env.example
APP_NAME=StewardAI
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=steward
DB_USERNAME=steward
DB_PASSWORD=steward_secret

REDIS_HOST=redis
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

PLAID_CLIENT_ID=your_client_id
PLAID_SECRET=your_secret
PLAID_ENV=sandbox

OLLAMA_HOST=http://ollama:11434

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="steward@localhost"
MAIL_FROM_NAME="StewardAI"
```

- [ ] **Step 5: Create docker-compose.yml**

```yaml
# docker-compose.yml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
      - ./steward:/var/www/html
    depends_on:
      - app

  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    volumes:
      - ./steward:/var/www/html
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    environment:
      - PHP_OPCACHE_ENABLE=0
    extra_hosts:
      - "host.docker.internal:host-gateway"

  worker:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    entrypoint: ["/bin/sh", "/var/www/html/../docker/worker/entrypoint.sh"]
    volumes:
      - ./steward:/var/www/html
      - ./docker/worker/entrypoint.sh:/docker/worker/entrypoint.sh
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy

  postgres:
    image: postgres:16-alpine
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: steward
      POSTGRES_USER: steward
      POSTGRES_PASSWORD: steward_secret
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U steward"]
      interval: 5s
      timeout: 5s
      retries: 5

  redis:
    image: redis:alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 5s
      retries: 5

  ollama:
    image: ollama/ollama
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: all
              capabilities: [gpu]

volumes:
  postgres_data:
  redis_data:
  ollama_data:
```

- [ ] **Step 6: Make worker entrypoint executable and commit**

```bash
chmod +x docker/worker/entrypoint.sh
git add docker-compose.yml docker/ .env.example
git commit -m "feat: add Docker infrastructure — compose, Dockerfile, nginx, worker"
```

---

### Task 2: Laravel Project Scaffolding

**Files:**
- Create: `steward/` (entire Laravel project)
- Modify: `steward/composer.json` (add packages)
- Modify: `steward/package.json` (add apexcharts)
- Modify: `docker/app/Dockerfile` (adjust for dev mode)

- [ ] **Step 1: Create the Laravel project**

Run from the `FinanceContainer` directory:

```bash
composer create-project laravel/laravel steward
```

- [ ] **Step 2: Create a dev-mode Dockerfile for local development**

Replace `docker/app/Dockerfile` with a dev-friendly version that doesn't copy files (uses volume mounts instead):

```dockerfile
# docker/app/Dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    git \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl

COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
```

- [ ] **Step 3: Install PHP packages**

```bash
cd steward
composer require livewire/livewire:^3.0
composer require mallardduck/blade-lucide-icons
composer require tomorrow-ideas/plaid-sdk-php
composer require laravel/breeze --dev
```

- [ ] **Step 4: Install Breeze with Livewire stack**

```bash
cd steward
php artisan breeze:install livewire
```

Select dark mode when prompted. This installs Tailwind CSS, Vite config, auth views, and Livewire components.

- [ ] **Step 5: Install ApexCharts via npm**

```bash
cd steward
npm install apexcharts
```

Add to `steward/resources/js/app.js`:

```javascript
import './bootstrap';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
```

- [ ] **Step 6: Configure the .env for Docker**

Copy `.env.example` from project root into `steward/.env`:

```bash
cp .env.example steward/.env
cd steward
php artisan key:generate
```

- [ ] **Step 7: Configure database and Redis in Laravel config**

Edit `steward/config/database.php` — verify the `pgsql` connection is the default. No changes needed if `.env` has `DB_CONNECTION=pgsql`.

Edit `steward/config/queue.php` — verify Redis is configured. No changes needed if `.env` has `QUEUE_CONNECTION=redis`.

- [ ] **Step 8: Build frontend assets and verify**

```bash
cd steward
npm run build
```

- [ ] **Step 9: Bring up Docker stack and verify**

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
```

Open `http://localhost` — should see the Laravel welcome page. Open `http://localhost/login` — should see the Breeze login page.

- [ ] **Step 10: Commit**

```bash
cd /Users/ahp-jamie/Documents/FinanceContainer
echo "steward/vendor/" >> .gitignore
echo "steward/node_modules/" >> .gitignore
echo "steward/.env" >> .gitignore
git add steward/ .gitignore docker/app/Dockerfile
git commit -m "feat: scaffold Laravel project with Livewire, Breeze, Plaid SDK, ApexCharts"
```

---

### Task 3: Enums

**Files:**
- Create: `steward/app/Enums/AccountType.php`
- Create: `steward/app/Enums/BudgetBucket.php`
- Create: `steward/app/Enums/PlaidConnectionStatus.php`
- Create: `steward/app/Enums/UserRole.php`

- [ ] **Step 1: Create all four enums**

```php
<?php
// steward/app/Enums/AccountType.php
namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
}
```

```php
<?php
// steward/app/Enums/BudgetBucket.php
namespace App\Enums;

enum BudgetBucket: string
{
    case Needs = 'needs';
    case Wants = 'wants';
    case Savings = 'savings';
}
```

```php
<?php
// steward/app/Enums/PlaidConnectionStatus.php
namespace App\Enums;

enum PlaidConnectionStatus: string
{
    case Active = 'active';
    case Error = 'error';
    case NeedsReauth = 'needs_reauth';
}
```

```php
<?php
// steward/app/Enums/UserRole.php
namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
```

- [ ] **Step 2: Commit**

```bash
git add steward/app/Enums/
git commit -m "feat: add enums for AccountType, BudgetBucket, PlaidConnectionStatus, UserRole"
```

---

### Task 4: Database Migrations

**Files:**
- Create: 11 migration files in `steward/database/migrations/`

- [ ] **Step 1: Generate all migration files**

```bash
cd steward
php artisan make:migration add_role_to_users_table
php artisan make:migration create_plaid_connections_table
php artisan make:migration create_accounts_table
php artisan make:migration create_categories_table
php artisan make:migration create_transactions_table
php artisan make:migration create_budgets_table
php artisan make:migration create_income_sources_table
php artisan make:migration create_summaries_table
php artisan make:migration create_chat_conversations_table
php artisan make:migration create_chat_messages_table
php artisan make:migration create_app_settings_table
```

- [ ] **Step 2: Write the add_role_to_users migration**

```php
// In the generated add_role_to_users_table migration
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('member')->after('password');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

- [ ] **Step 3: Write the plaid_connections migration**

```php
public function up(): void
{
    Schema::create('plaid_connections', function (Blueprint $table) {
        $table->id();
        $table->text('access_token');
        $table->string('item_id');
        $table->string('institution_name');
        $table->string('cursor')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });
}
```

- [ ] **Step 4: Write the accounts migration**

```php
public function up(): void
{
    Schema::create('accounts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('plaid_connection_id')->constrained()->cascadeOnDelete();
        $table->string('plaid_account_id');
        $table->string('name');
        $table->string('type');
        $table->decimal('current_balance', 12, 2)->default(0);
        $table->decimal('available_balance', 12, 2)->default(0);
        $table->timestamp('last_synced_at')->nullable();
        $table->timestamps();
    });
}
```

- [ ] **Step 5: Write the categories migration**

```php
public function up(): void
{
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('icon')->default('tag');
        $table->string('default_bucket');
        $table->boolean('is_essential')->default(false);
        $table->boolean('is_system')->default(false);
        $table->timestamps();
    });
}
```

- [ ] **Step 6: Write the transactions migration**

```php
public function up(): void
{
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('account_id')->constrained()->cascadeOnDelete();
        $table->string('plaid_transaction_id')->unique();
        $table->decimal('amount', 10, 2);
        $table->date('date');
        $table->string('merchant_name')->nullable();
        $table->string('description');
        $table->string('plaid_category')->nullable();
        $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
        $table->decimal('categorization_confidence', 3, 2)->default(0);
        $table->boolean('needs_review')->default(false);
        $table->boolean('is_recurring')->default(false);
        $table->string('budget_bucket')->nullable();
        $table->timestamps();

        $table->index(['account_id', 'date']);
        $table->index('needs_review');
    });
}
```

- [ ] **Step 7: Write the budgets migration**

```php
public function up(): void
{
    Schema::create('budgets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('category_id')->constrained()->cascadeOnDelete();
        $table->string('month', 7);
        $table->decimal('budgeted_amount', 10, 2);
        $table->string('bucket');
        $table->timestamps();

        $table->unique(['category_id', 'month']);
    });
}
```

- [ ] **Step 8: Write the income_sources migration**

```php
public function up(): void
{
    Schema::create('income_sources', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('amount', 10, 2);
        $table->string('frequency');
        $table->date('next_pay_date');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}
```

- [ ] **Step 9: Write the summaries migration**

```php
public function up(): void
{
    Schema::create('summaries', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->date('period_start');
        $table->date('period_end');
        $table->decimal('total_income', 10, 2)->default(0);
        $table->decimal('total_spent', 10, 2)->default(0);
        $table->decimal('needs_spent', 10, 2)->default(0);
        $table->decimal('wants_spent', 10, 2)->default(0);
        $table->decimal('savings_spent', 10, 2)->default(0);
        $table->text('ai_analysis')->nullable();
        $table->text('ai_advice')->nullable();
        $table->json('habit_flags')->nullable();
        $table->timestamps();

        $table->unique(['type', 'period_start']);
    });
}
```

- [ ] **Step 10: Write the chat_conversations migration**

```php
public function up(): void
{
    Schema::create('chat_conversations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->timestamps();
    });
}
```

- [ ] **Step 11: Write the chat_messages migration**

```php
public function up(): void
{
    Schema::create('chat_messages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->string('role');
        $table->text('content');
        $table->timestamps();
    });
}
```

- [ ] **Step 12: Write the app_settings migration**

```php
public function up(): void
{
    Schema::create('app_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->json('value');
        $table->timestamps();
    });
}
```

- [ ] **Step 13: Run migrations**

```bash
docker compose exec app php artisan migrate
```

Expected: All migrations run successfully, no errors.

- [ ] **Step 14: Commit**

```bash
git add steward/database/migrations/
git commit -m "feat: add all database migrations for Phase 1 schema"
```

---

### Task 5: Eloquent Models

**Files:**
- Modify: `steward/app/Models/User.php`
- Create: `steward/app/Models/PlaidConnection.php`
- Create: `steward/app/Models/Account.php`
- Create: `steward/app/Models/Category.php`
- Create: `steward/app/Models/Transaction.php`
- Create: `steward/app/Models/Budget.php`
- Create: `steward/app/Models/IncomeSource.php`
- Create: `steward/app/Models/Summary.php`
- Create: `steward/app/Models/ChatConversation.php`
- Create: `steward/app/Models/ChatMessage.php`
- Create: `steward/app/Models/AppSetting.php`
- Test: `steward/tests/Unit/Models/PlaidConnectionTest.php`
- Test: `steward/tests/Unit/Models/AccountTest.php`
- Test: `steward/tests/Unit/Models/CategoryTest.php`
- Test: `steward/tests/Unit/Models/TransactionTest.php`

- [ ] **Step 1: Write model relationship tests**

```php
<?php
// steward/tests/Unit/Models/PlaidConnectionTest.php
namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\PlaidConnection;
use App\Enums\PlaidConnectionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaidConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_many_accounts(): void
    {
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        $this->assertTrue($connection->accounts->contains($account));
    }

    public function test_casts_status_to_enum(): void
    {
        $connection = PlaidConnection::factory()->create(['status' => 'active']);

        $this->assertInstanceOf(PlaidConnectionStatus::class, $connection->status);
        $this->assertEquals(PlaidConnectionStatus::Active, $connection->status);
    }

    public function test_encrypts_access_token(): void
    {
        $connection = PlaidConnection::factory()->create(['access_token' => 'test-token-123']);

        $raw = \DB::table('plaid_connections')->where('id', $connection->id)->value('access_token');
        $this->assertNotEquals('test-token-123', $raw);
        $this->assertEquals('test-token-123', $connection->access_token);
    }
}
```

```php
<?php
// steward/tests/Unit/Models/AccountTest.php
namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Enums\AccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_plaid_connection(): void
    {
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        $this->assertTrue($account->plaidConnection->is($connection));
    }

    public function test_has_many_transactions(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create(['account_id' => $account->id]);

        $this->assertTrue($account->transactions->contains($transaction));
    }

    public function test_casts_type_to_enum(): void
    {
        $account = Account::factory()->create(['type' => 'checking']);

        $this->assertInstanceOf(AccountType::class, $account->type);
        $this->assertEquals(AccountType::Checking, $account->type);
    }
}
```

```php
<?php
// steward/tests/Unit/Models/CategoryTest.php
namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\Budget;
use App\Enums\BudgetBucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_many_transactions(): void
    {
        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->transactions->contains($transaction));
    }

    public function test_has_many_budgets(): void
    {
        $category = Category::factory()->create();
        $budget = Budget::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->budgets->contains($budget));
    }

    public function test_average_spend_calculation(): void
    {
        $category = Category::factory()->create();
        $account = \App\Models\Account::factory()->create();

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 100.00,
            'date' => now()->subMonth(),
        ]);
        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00,
            'date' => now()->subMonths(2),
        ]);

        $this->assertEquals(150.00, $category->averageSpend(3));
    }
}
```

```php
<?php
// steward/tests/Unit/Models/TransactionTest.php
namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_account(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create(['account_id' => $account->id]);

        $this->assertTrue($transaction->account->is($account));
    }

    public function test_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($transaction->category->is($category));
    }

    public function test_category_is_nullable(): void
    {
        $transaction = Transaction::factory()->create(['category_id' => null]);

        $this->assertNull($transaction->category);
    }

    public function test_scope_needs_review(): void
    {
        Transaction::factory()->create(['needs_review' => true]);
        Transaction::factory()->create(['needs_review' => false]);

        $this->assertCount(1, Transaction::where('needs_review', true)->get());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="PlaidConnectionTest|AccountTest|CategoryTest|TransactionTest"
```

Expected: FAIL — models and factories don't exist yet.

- [ ] **Step 3: Create model factories**

```php
<?php
// steward/database/factories/PlaidConnectionFactory.php
namespace Database\Factories;

use App\Enums\PlaidConnectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaidConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'access_token' => $this->faker->sha256(),
            'item_id' => 'item_' . $this->faker->unique()->bothify('####????'),
            'institution_name' => $this->faker->company(),
            'cursor' => null,
            'status' => PlaidConnectionStatus::Active,
        ];
    }
}
```

```php
<?php
// steward/database/factories/AccountFactory.php
namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\PlaidConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plaid_connection_id' => PlaidConnection::factory(),
            'plaid_account_id' => 'acc_' . $this->faker->unique()->bothify('####????'),
            'name' => $this->faker->randomElement(['Checking', 'Savings']),
            'type' => $this->faker->randomElement(AccountType::cases()),
            'current_balance' => $this->faker->randomFloat(2, 100, 10000),
            'available_balance' => $this->faker->randomFloat(2, 100, 10000),
            'last_synced_at' => null,
        ];
    }
}
```

```php
<?php
// steward/database/factories/CategoryFactory.php
namespace Database\Factories;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'icon' => 'tag',
            'default_bucket' => $this->faker->randomElement(BudgetBucket::cases()),
            'is_essential' => false,
            'is_system' => false,
        ];
    }
}
```

```php
<?php
// steward/database/factories/TransactionFactory.php
namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'plaid_transaction_id' => 'txn_' . $this->faker->unique()->bothify('##########'),
            'amount' => $this->faker->randomFloat(2, 1, 500),
            'date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'merchant_name' => $this->faker->company(),
            'description' => $this->faker->sentence(3),
            'plaid_category' => null,
            'category_id' => null,
            'categorization_confidence' => 0,
            'needs_review' => false,
            'is_recurring' => false,
            'budget_bucket' => null,
        ];
    }
}
```

```php
<?php
// steward/database/factories/BudgetFactory.php
namespace Database\Factories;

use App\Enums\BudgetBucket;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'month' => now()->format('Y-m'),
            'budgeted_amount' => $this->faker->randomFloat(2, 50, 2000),
            'bucket' => $this->faker->randomElement(BudgetBucket::cases()),
        ];
    }
}
```

- [ ] **Step 4: Modify the User model**

Add role casting and chat relationship to the existing `steward/app/Models/User.php`:

```php
<?php
namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
```

- [ ] **Step 5: Create PlaidConnection model**

```php
<?php
// steward/app/Models/PlaidConnection.php
namespace App\Models;

use App\Enums\PlaidConnectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaidConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_token',
        'item_id',
        'institution_name',
        'cursor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'status' => PlaidConnectionStatus::class,
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
```

- [ ] **Step 6: Create Account model**

```php
<?php
// steward/app/Models/Account.php
namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaid_connection_id',
        'plaid_account_id',
        'name',
        'type',
        'current_balance',
        'available_balance',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'current_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }

    public function plaidConnection(): BelongsTo
    {
        return $this->belongsTo(PlaidConnection::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
```

- [ ] **Step 7: Create Category model**

```php
<?php
// steward/app/Models/Category.php
namespace App\Models;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'default_bucket',
        'is_essential',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'default_bucket' => BudgetBucket::class,
            'is_essential' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function averageSpend(int $months = 3): float
    {
        $since = now()->subMonths($months);

        $total = $this->transactions()
            ->where('date', '>=', $since)
            ->sum('amount');

        return round($total / max($months, 1), 2);
    }
}
```

- [ ] **Step 8: Create Transaction model**

```php
<?php
// steward/app/Models/Transaction.php
namespace App\Models;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'plaid_transaction_id',
        'amount',
        'date',
        'merchant_name',
        'description',
        'plaid_category',
        'category_id',
        'categorization_confidence',
        'needs_review',
        'is_recurring',
        'budget_bucket',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'categorization_confidence' => 'decimal:2',
            'needs_review' => 'boolean',
            'is_recurring' => 'boolean',
            'budget_bucket' => BudgetBucket::class,
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
}
```

- [ ] **Step 9: Create remaining models (Budget, IncomeSource, Summary, ChatConversation, ChatMessage, AppSetting)**

```php
<?php
// steward/app/Models/Budget.php
namespace App\Models;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'month',
        'budgeted_amount',
        'bucket',
    ];

    protected function casts(): array
    {
        return [
            'budgeted_amount' => 'decimal:2',
            'bucket' => BudgetBucket::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

```php
<?php
// steward/app/Models/IncomeSource.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'frequency',
        'next_pay_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_pay_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function monthlyAmount(): float
    {
        return match ($this->frequency) {
            'weekly' => round($this->amount * 52 / 12, 2),
            'biweekly' => round($this->amount * 26 / 12, 2),
            'monthly' => (float) $this->amount,
            default => 0,
        };
    }
}
```

```php
<?php
// steward/app/Models/Summary.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'period_start',
        'period_end',
        'total_income',
        'total_spent',
        'needs_spent',
        'wants_spent',
        'savings_spent',
        'ai_analysis',
        'ai_advice',
        'habit_flags',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_income' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'needs_spent' => 'decimal:2',
            'wants_spent' => 'decimal:2',
            'savings_spent' => 'decimal:2',
            'habit_flags' => 'array',
        ];
    }
}
```

```php
<?php
// steward/app/Models/ChatConversation.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
```

```php
<?php
// steward/app/Models/ChatMessage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'content',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

```php
<?php
// steward/app/Models/AppSetting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
```

- [ ] **Step 10: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="PlaidConnectionTest|AccountTest|CategoryTest|TransactionTest"
```

Expected: All tests PASS.

- [ ] **Step 11: Commit**

```bash
git add steward/app/Models/ steward/database/factories/ steward/tests/Unit/Models/
git commit -m "feat: add all Eloquent models with relationships, factories, and tests"
```

---

### Task 6: Database Seeders

**Files:**
- Create: `steward/database/seeders/CategorySeeder.php`
- Create: `steward/database/seeders/AppSettingSeeder.php`
- Create: `steward/database/seeders/UserSeeder.php`
- Modify: `steward/database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create CategorySeeder**

```php
<?php
// steward/database/seeders/CategorySeeder.php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Needs (essential)
            ['name' => 'Mortgage', 'icon' => 'home', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Electric', 'icon' => 'zap', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Gas Utility', 'icon' => 'flame', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Water', 'icon' => 'droplets', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Internet', 'icon' => 'wifi', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Phone', 'icon' => 'smartphone', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Groceries', 'icon' => 'shopping-cart', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Gasoline', 'icon' => 'fuel', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Car Insurance', 'icon' => 'shield', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Health Insurance', 'icon' => 'heart-pulse', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Car Maintenance', 'icon' => 'wrench', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Home Repair', 'icon' => 'hammer', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Medical', 'icon' => 'stethoscope', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Childcare', 'icon' => 'baby', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],

            // Wants
            ['name' => 'Dining Out', 'icon' => 'utensils', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Coffee', 'icon' => 'coffee', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Entertainment', 'icon' => 'film', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Subscriptions', 'icon' => 'repeat', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Shopping', 'icon' => 'shopping-bag', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Hobbies', 'icon' => 'puzzle', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Clothing', 'icon' => 'shirt', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Personal Care', 'icon' => 'sparkles', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Gifts', 'icon' => 'gift', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],

            // Savings
            ['name' => 'Emergency Fund', 'icon' => 'piggy-bank', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Car Fund', 'icon' => 'car', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Renovation Fund', 'icon' => 'paint-roller', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Tithe', 'icon' => 'church', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],

            // Uncategorized fallback
            ['name' => 'Uncategorized', 'icon' => 'help-circle', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
```

- [ ] **Step 2: Create AppSettingSeeder**

```php
<?php
// steward/database/seeders/AppSettingSeeder.php
namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'sync_schedule' => '0 4 * * *',
            'categorization_confidence_threshold' => 0.9,
            'budget_ratios' => ['needs' => 50, 'wants' => 30, 'savings' => 20],
            'email_recipients' => [],
            'ollama_model' => 'llama3.1:70b-instruct-q4_K_M',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
```

- [ ] **Step 3: Create UserSeeder**

```php
<?php
// steward/database/seeders/UserSeeder.php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@steward.local'],
            [
                'name' => 'Jamie',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'member@steward.local'],
            [
                'name' => 'Wife',
                'password' => Hash::make('password'),
                'role' => 'member',
            ]
        );
    }
}
```

- [ ] **Step 4: Update DatabaseSeeder**

```php
<?php
// steward/database/seeders/DatabaseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            AppSettingSeeder::class,
        ]);
    }
}
```

- [ ] **Step 5: Run seeders**

```bash
docker compose exec app php artisan db:seed
```

Expected: No errors. Verify with:

```bash
docker compose exec app php artisan tinker --execute="echo App\Models\User::count() . ' users, ' . App\Models\Category::count() . ' categories, ' . App\Models\AppSetting::count() . ' settings';"
```

Expected output: `2 users, 28 categories, 5 settings`

- [ ] **Step 6: Commit**

```bash
git add steward/database/seeders/
git commit -m "feat: add seeders for users, categories, and app settings"
```

---

### Task 7: App Layout with Sidebar

**Files:**
- Create: `steward/resources/views/components/layouts/app.blade.php`
- Create: `steward/app/Livewire/Layout/Sidebar.php`
- Create: `steward/resources/views/livewire/layout/sidebar.blade.php`
- Modify: `steward/routes/web.php`

- [ ] **Step 1: Create the Sidebar Livewire component**

```php
<?php
// steward/app/Livewire/Layout/Sidebar.php
namespace App\Livewire\Layout;

use Livewire\Component;

class Sidebar extends Component
{
    public function render()
    {
        return view('livewire.layout.sidebar');
    }
}
```

- [ ] **Step 2: Create the sidebar Blade template**

```blade
{{-- steward/resources/views/livewire/layout/sidebar.blade.php --}}
<nav class="flex flex-col w-64 min-h-screen bg-gray-950 border-r border-gray-800">
    {{-- Logo --}}
    <div class="px-6 py-5 border-b border-gray-800">
        <span class="text-lg font-semibold text-blue-400 tracking-tight">StewardAI</span>
    </div>

    {{-- Navigation --}}
    <div class="flex flex-col flex-1 px-3 py-4 space-y-1">
        @php
            $links = [
                ['route' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                ['route' => 'transactions', 'icon' => 'arrow-left-right', 'label' => 'Transactions'],
                ['route' => 'budgets', 'icon' => 'wallet', 'label' => 'Budgets'],
                ['route' => 'categories', 'icon' => 'tags', 'label' => 'Categories'],
                ['route' => 'accounts', 'icon' => 'landmark', 'label' => 'Accounts'],
                ['route' => 'summaries', 'icon' => 'file-text', 'label' => 'Summaries'],
                ['route' => 'chat', 'icon' => 'message-square', 'label' => 'Chat'],
            ];
        @endphp

        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
               @class([
                   'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                   'bg-gray-800/50 text-white' => request()->routeIs($link['route']),
                   'text-gray-400 hover:text-white hover:bg-gray-800/30' => !request()->routeIs($link['route']),
               ])>
                <x-dynamic-component :component="'lucide-' . $link['icon']" class="w-5 h-5 flex-shrink-0" />
                {{ $link['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Settings (bottom) --}}
    <div class="px-3 py-4 border-t border-gray-800">
        <a href="{{ route('settings') }}"
           @class([
               'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
               'bg-gray-800/50 text-white' => request()->routeIs('settings.*') || request()->routeIs('settings'),
               'text-gray-400 hover:text-white hover:bg-gray-800/30' => !request()->routeIs('settings.*') && !request()->routeIs('settings'),
           ])>
            <x-lucide-settings class="w-5 h-5 flex-shrink-0" />
            Settings
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/30 transition-colors w-full">
                <x-lucide-log-out class="w-5 h-5 flex-shrink-0" />
                Sign Out
            </button>
        </form>
    </div>
</nav>
```

- [ ] **Step 3: Create the app layout**

```blade
{{-- steward/resources/views/components/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'StewardAI' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 text-gray-100 antialiased">
    <div class="flex min-h-screen">
        <livewire:layout.sidebar />

        <main class="flex-1 p-8 overflow-auto">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
```

- [ ] **Step 4: Set up routes with placeholder pages**

```php
<?php
// steward/routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::get('/dashboard', fn () => view('pages.dashboard'))
        ->name('dashboard');

    Route::get('/transactions', fn () => view('pages.transactions'))
        ->name('transactions');

    Route::get('/budgets', fn () => view('pages.budgets'))
        ->name('budgets');

    Route::get('/categories', fn () => view('pages.categories'))
        ->name('categories');

    Route::get('/accounts', fn () => view('pages.accounts'))
        ->name('accounts');

    Route::get('/summaries', fn () => view('pages.summaries'))
        ->name('summaries');

    Route::get('/chat', fn () => view('pages.chat'))
        ->name('chat');

    Route::get('/settings', fn () => view('pages.settings'))
        ->name('settings');
});
```

- [ ] **Step 5: Create placeholder page views**

Create `steward/resources/views/pages/` directory. Each page is a minimal placeholder that uses the app layout:

```blade
{{-- steward/resources/views/pages/dashboard.blade.php --}}
<x-layouts.app title="Dashboard">
    <h1 class="text-2xl font-semibold">Dashboard</h1>
    <p class="text-gray-400 mt-2">Coming in Phase 3.</p>
</x-layouts.app>
```

Create the same pattern for each page file: `transactions.blade.php`, `budgets.blade.php`, `categories.blade.php`, `accounts.blade.php`, `summaries.blade.php`, `chat.blade.php`, `settings.blade.php`. Change the `<h1>` text and `title` attribute to match each page name.

- [ ] **Step 6: Build assets and verify**

```bash
cd steward && npm run build
```

Then open `http://localhost`, log in with `admin@steward.local` / `password`. Verify the sidebar appears with all navigation links and Lucide icons. Click through each link to confirm routing works.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Livewire/Layout/ steward/resources/views/ steward/routes/web.php
git commit -m "feat: add app layout with sidebar navigation and placeholder pages"
```

---

### Task 8: PlaidService (TDD)

**Files:**
- Create: `steward/app/Services/PlaidService.php`
- Test: `steward/tests/Unit/Services/PlaidServiceTest.php`

- [ ] **Step 1: Write failing tests for PlaidService**

```php
<?php
// steward/tests/Unit/Services/PlaidServiceTest.php
namespace Tests\Unit\Services;

use App\Models\PlaidConnection;
use App\Services\PlaidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaidServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlaidService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.plaid.client_id' => 'test_client_id',
            'services.plaid.secret' => 'test_secret',
            'services.plaid.env' => 'sandbox',
        ]);
        $this->service = new PlaidService();
    }

    public function test_create_link_token(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/link/token/create' => Http::response([
                'link_token' => 'link-sandbox-abc123',
                'expiration' => '2026-05-01T00:00:00Z',
            ]),
        ]);

        $result = $this->service->createLinkToken('user-1');

        $this->assertEquals('link-sandbox-abc123', $result['link_token']);
        Http::assertSent(function ($request) {
            return $request['client_id'] === 'test_client_id'
                && $request['user']['client_user_id'] === 'user-1'
                && in_array('transactions', $request['products']);
        });
    }

    public function test_exchange_public_token(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/item/public_token/exchange' => Http::response([
                'access_token' => 'access-sandbox-xyz789',
                'item_id' => 'item_abc123',
            ]),
        ]);

        $result = $this->service->exchangePublicToken('public-sandbox-token');

        $this->assertEquals('access-sandbox-xyz789', $result['access_token']);
        $this->assertEquals('item_abc123', $result['item_id']);
    }

    public function test_sync_transactions(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/transactions/sync' => Http::response([
                'added' => [
                    [
                        'transaction_id' => 'txn_001',
                        'account_id' => 'acc_001',
                        'amount' => 12.50,
                        'date' => '2026-04-28',
                        'merchant_name' => 'Starbucks',
                        'name' => 'STARBUCKS COFFEE',
                        'personal_finance_category' => ['primary' => 'FOOD_AND_DRINK'],
                    ],
                ],
                'modified' => [],
                'removed' => [],
                'next_cursor' => 'cursor_abc',
                'has_more' => false,
            ]),
        ]);

        $result = $this->service->syncTransactions('access-token', null);

        $this->assertCount(1, $result['added']);
        $this->assertEquals('txn_001', $result['added'][0]['transaction_id']);
        $this->assertEquals('cursor_abc', $result['next_cursor']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_accounts(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/accounts/get' => Http::response([
                'accounts' => [
                    [
                        'account_id' => 'acc_001',
                        'name' => 'Checking',
                        'type' => 'depository',
                        'subtype' => 'checking',
                        'balances' => [
                            'current' => 1247.33,
                            'available' => 1200.00,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getAccounts('access-token');

        $this->assertCount(1, $result);
        $this->assertEquals('Checking', $result[0]['name']);
        $this->assertEquals(1247.33, $result[0]['balances']['current']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="PlaidServiceTest"
```

Expected: FAIL — `PlaidService` class doesn't exist.

- [ ] **Step 3: Add Plaid config**

Add to `steward/config/services.php` inside the return array:

```php
'plaid' => [
    'client_id' => env('PLAID_CLIENT_ID'),
    'secret' => env('PLAID_SECRET'),
    'env' => env('PLAID_ENV', 'sandbox'),
],
```

- [ ] **Step 4: Implement PlaidService**

```php
<?php
// steward/app/Services/PlaidService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class PlaidService
{
    private string $baseUrl;
    private string $clientId;
    private string $secret;

    public function __construct()
    {
        $env = config('services.plaid.env', 'sandbox');
        $this->baseUrl = match ($env) {
            'production' => 'https://production.plaid.com',
            'development' => 'https://development.plaid.com',
            default => 'https://sandbox.plaid.com',
        };
        $this->clientId = config('services.plaid.client_id');
        $this->secret = config('services.plaid.secret');
    }

    public function createLinkToken(string $userId): array
    {
        $response = Http::post("{$this->baseUrl}/link/token/create", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'user' => ['client_user_id' => $userId],
            'client_name' => config('app.name'),
            'products' => ['transactions'],
            'country_codes' => ['US'],
            'language' => 'en',
        ]);

        return $response->json();
    }

    public function exchangePublicToken(string $publicToken): array
    {
        $response = Http::post("{$this->baseUrl}/item/public_token/exchange", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'public_token' => $publicToken,
        ]);

        return $response->json();
    }

    public function syncTransactions(string $accessToken, ?string $cursor): array
    {
        $body = [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ];

        if ($cursor) {
            $body['cursor'] = $cursor;
        }

        $response = Http::post("{$this->baseUrl}/transactions/sync", $body);

        return $response->json();
    }

    public function getAccounts(string $accessToken): array
    {
        $response = Http::post("{$this->baseUrl}/accounts/get", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);

        return $response->json()['accounts'];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="PlaidServiceTest"
```

Expected: All 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add steward/app/Services/PlaidService.php steward/tests/Unit/Services/PlaidServiceTest.php steward/config/services.php
git commit -m "feat: add PlaidService with link token, token exchange, sync, and account fetching"
```

---

### Task 9: PlaidSyncJob (TDD)

**Files:**
- Create: `steward/app/Jobs/PlaidSyncJob.php`
- Test: `steward/tests/Feature/Jobs/PlaidSyncJobTest.php`

- [ ] **Step 1: Write failing test for PlaidSyncJob**

```php
<?php
// steward/tests/Feature/Jobs/PlaidSyncJobTest.php
namespace Tests\Feature\Jobs;

use App\Enums\AccountType;
use App\Enums\PlaidConnectionStatus;
use App\Jobs\PlaidSyncJob;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Services\PlaidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlaidSyncJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_new_transactions(): void
    {
        $connection = PlaidConnection::factory()->create([
            'cursor' => null,
            'status' => PlaidConnectionStatus::Active,
        ]);

        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_001',
            'type' => AccountType::Checking,
        ]);

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('syncTransactions')
            ->once()
            ->with($connection->access_token, null)
            ->andReturn([
                'added' => [
                    [
                        'transaction_id' => 'txn_001',
                        'account_id' => 'acc_001',
                        'amount' => -12.50,
                        'date' => '2026-04-28',
                        'merchant_name' => 'Starbucks',
                        'name' => 'STARBUCKS COFFEE',
                        'personal_finance_category' => ['primary' => 'FOOD_AND_DRINK'],
                    ],
                ],
                'modified' => [],
                'removed' => [],
                'next_cursor' => 'cursor_abc',
                'has_more' => false,
            ]);

        $mock->shouldReceive('getAccounts')
            ->once()
            ->andReturn([
                [
                    'account_id' => 'acc_001',
                    'name' => 'Checking',
                    'type' => 'depository',
                    'subtype' => 'checking',
                    'balances' => ['current' => 1247.33, 'available' => 1200.00],
                ],
            ]);

        $this->app->instance(PlaidService::class, $mock);

        (new PlaidSyncJob($connection))->handle($mock);

        $this->assertDatabaseHas('transactions', [
            'plaid_transaction_id' => 'txn_001',
            'account_id' => $account->id,
            'amount' => 12.50,
            'merchant_name' => 'Starbucks',
            'description' => 'STARBUCKS COFFEE',
            'needs_review' => true,
        ]);

        $connection->refresh();
        $this->assertEquals('cursor_abc', $connection->cursor);

        $account->refresh();
        $this->assertEquals('1247.33', $account->current_balance);
        $this->assertEquals('1200.00', $account->available_balance);
    }

    public function test_handles_removed_transactions(): void
    {
        $connection = PlaidConnection::factory()->create([
            'cursor' => 'old_cursor',
        ]);

        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_001',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'plaid_transaction_id' => 'txn_to_remove',
        ]);

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('syncTransactions')
            ->once()
            ->andReturn([
                'added' => [],
                'modified' => [],
                'removed' => [['transaction_id' => 'txn_to_remove']],
                'next_cursor' => 'new_cursor',
                'has_more' => false,
            ]);

        $mock->shouldReceive('getAccounts')->once()->andReturn([]);

        (new PlaidSyncJob($connection))->handle($mock);

        $this->assertDatabaseMissing('transactions', [
            'plaid_transaction_id' => 'txn_to_remove',
        ]);
    }

    public function test_paginates_with_has_more(): void
    {
        $connection = PlaidConnection::factory()->create(['cursor' => null]);
        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_001',
        ]);

        $mock = Mockery::mock(PlaidService::class);

        $mock->shouldReceive('syncTransactions')
            ->with($connection->access_token, null)
            ->once()
            ->andReturn([
                'added' => [
                    ['transaction_id' => 'txn_page1', 'account_id' => 'acc_001', 'amount' => -10, 'date' => '2026-04-28', 'merchant_name' => 'Store A', 'name' => 'STORE A', 'personal_finance_category' => null],
                ],
                'modified' => [],
                'removed' => [],
                'next_cursor' => 'cursor_page2',
                'has_more' => true,
            ]);

        $mock->shouldReceive('syncTransactions')
            ->with($connection->access_token, 'cursor_page2')
            ->once()
            ->andReturn([
                'added' => [
                    ['transaction_id' => 'txn_page2', 'account_id' => 'acc_001', 'amount' => -20, 'date' => '2026-04-28', 'merchant_name' => 'Store B', 'name' => 'STORE B', 'personal_finance_category' => null],
                ],
                'modified' => [],
                'removed' => [],
                'next_cursor' => 'cursor_final',
                'has_more' => false,
            ]);

        $mock->shouldReceive('getAccounts')->once()->andReturn([]);

        (new PlaidSyncJob($connection))->handle($mock);

        $this->assertEquals(2, Transaction::count());
        $connection->refresh();
        $this->assertEquals('cursor_final', $connection->cursor);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="PlaidSyncJobTest"
```

Expected: FAIL — `PlaidSyncJob` class doesn't exist.

- [ ] **Step 3: Implement PlaidSyncJob**

```php
<?php
// steward/app/Jobs/PlaidSyncJob.php
namespace App\Jobs;

use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Services\PlaidService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PlaidSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public PlaidConnection $connection,
    ) {
        $this->onQueue('default');
    }

    public function handle(PlaidService $plaid): void
    {
        $cursor = $this->connection->cursor;

        do {
            $result = $plaid->syncTransactions(
                $this->connection->access_token,
                $cursor,
            );

            $this->processAdded($result['added']);
            $this->processModified($result['modified']);
            $this->processRemoved($result['removed']);

            $cursor = $result['next_cursor'];
        } while ($result['has_more']);

        $this->connection->update([
            'cursor' => $cursor,
            'status' => 'active',
        ]);

        $this->updateBalances($plaid);

        Log::info('Plaid sync complete', [
            'connection_id' => $this->connection->id,
            'institution' => $this->connection->institution_name,
        ]);
    }

    private function processAdded(array $transactions): void
    {
        foreach ($transactions as $txn) {
            $account = Account::where('plaid_account_id', $txn['account_id'])->first();
            if (! $account) {
                continue;
            }

            Transaction::updateOrCreate(
                ['plaid_transaction_id' => $txn['transaction_id']],
                [
                    'account_id' => $account->id,
                    'amount' => abs($txn['amount']),
                    'date' => $txn['date'],
                    'merchant_name' => $txn['merchant_name'] ?? null,
                    'description' => $txn['name'],
                    'plaid_category' => $txn['personal_finance_category']['primary'] ?? null,
                    'needs_review' => true,
                ]
            );
        }
    }

    private function processModified(array $transactions): void
    {
        foreach ($transactions as $txn) {
            $existing = Transaction::where('plaid_transaction_id', $txn['transaction_id'])->first();
            if (! $existing) {
                continue;
            }

            $existing->update([
                'amount' => abs($txn['amount']),
                'date' => $txn['date'],
                'merchant_name' => $txn['merchant_name'] ?? null,
                'description' => $txn['name'],
                'plaid_category' => $txn['personal_finance_category']['primary'] ?? null,
            ]);
        }
    }

    private function processRemoved(array $transactions): void
    {
        $ids = array_column($transactions, 'transaction_id');
        if (! empty($ids)) {
            Transaction::whereIn('plaid_transaction_id', $ids)->delete();
        }
    }

    private function updateBalances(PlaidService $plaid): void
    {
        $accounts = $plaid->getAccounts($this->connection->access_token);

        foreach ($accounts as $plaidAccount) {
            Account::where('plaid_account_id', $plaidAccount['account_id'])->update([
                'current_balance' => $plaidAccount['balances']['current'] ?? 0,
                'available_balance' => $plaidAccount['balances']['available'] ?? 0,
                'last_synced_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="PlaidSyncJobTest"
```

Expected: All 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add steward/app/Jobs/PlaidSyncJob.php steward/tests/Feature/Jobs/PlaidSyncJobTest.php
git commit -m "feat: add PlaidSyncJob with pagination, modified/removed handling, and balance updates"
```

---

### Task 10: Plaid Link Livewire Component

**Files:**
- Create: `steward/app/Livewire/Plaid/PlaidLink.php`
- Create: `steward/resources/views/livewire/plaid/plaid-link.blade.php`
- Test: `steward/tests/Feature/Livewire/PlaidLinkTest.php`
- Modify: `steward/resources/views/pages/accounts.blade.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/PlaidLinkTest.php
namespace Tests\Feature\Livewire;

use App\Livewire\Plaid\PlaidLink;
use App\Models\User;
use App\Services\PlaidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PlaidLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_link_token(): void
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('createLinkToken')
            ->once()
            ->andReturn(['link_token' => 'link-sandbox-test']);

        $this->app->instance(PlaidService::class, $mock);

        Livewire::actingAs($user)
            ->test(PlaidLink::class)
            ->call('createLinkToken')
            ->assertSet('linkToken', 'link-sandbox-test');
    }

    public function test_can_exchange_token_and_create_connection(): void
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('exchangePublicToken')
            ->once()
            ->with('public-sandbox-token')
            ->andReturn([
                'access_token' => 'access-sandbox-xyz',
                'item_id' => 'item_abc',
            ]);

        $mock->shouldReceive('getAccounts')
            ->once()
            ->andReturn([
                [
                    'account_id' => 'acc_001',
                    'name' => 'Checking',
                    'type' => 'depository',
                    'subtype' => 'checking',
                    'balances' => ['current' => 1000, 'available' => 950],
                ],
                [
                    'account_id' => 'acc_002',
                    'name' => 'Savings',
                    'type' => 'depository',
                    'subtype' => 'savings',
                    'balances' => ['current' => 5000, 'available' => 5000],
                ],
            ]);

        $this->app->instance(PlaidService::class, $mock);

        Livewire::actingAs($user)
            ->test(PlaidLink::class)
            ->call('onSuccess', 'public-sandbox-token', ['institution' => ['name' => 'Test Bank']])
            ->assertDispatched('plaid-connected');

        $this->assertDatabaseHas('plaid_connections', [
            'item_id' => 'item_abc',
            'institution_name' => 'Test Bank',
        ]);

        $this->assertDatabaseHas('accounts', ['plaid_account_id' => 'acc_001', 'name' => 'Checking']);
        $this->assertDatabaseHas('accounts', ['plaid_account_id' => 'acc_002', 'name' => 'Savings']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="PlaidLinkTest"
```

Expected: FAIL — `PlaidLink` component doesn't exist.

- [ ] **Step 3: Implement PlaidLink component**

```php
<?php
// steward/app/Livewire/Plaid/PlaidLink.php
namespace App\Livewire\Plaid;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Services\PlaidService;
use Livewire\Component;

class PlaidLink extends Component
{
    public ?string $linkToken = null;
    public bool $connecting = false;
    public ?string $error = null;

    public function createLinkToken(PlaidService $plaid): void
    {
        $result = $plaid->createLinkToken((string) auth()->id());
        $this->linkToken = $result['link_token'];
    }

    public function onSuccess(string $publicToken, array $metadata, ?PlaidService $plaid = null): void
    {
        $plaid ??= app(PlaidService::class);

        $this->connecting = true;
        $this->error = null;

        $result = $plaid->exchangePublicToken($publicToken);

        $connection = PlaidConnection::create([
            'access_token' => $result['access_token'],
            'item_id' => $result['item_id'],
            'institution_name' => $metadata['institution']['name'] ?? 'Unknown',
            'status' => 'active',
        ]);

        $accounts = $plaid->getAccounts($result['access_token']);

        foreach ($accounts as $plaidAccount) {
            $type = match ($plaidAccount['subtype'] ?? $plaidAccount['type']) {
                'checking' => AccountType::Checking,
                'savings' => AccountType::Savings,
                default => AccountType::Checking,
            };

            Account::create([
                'plaid_connection_id' => $connection->id,
                'plaid_account_id' => $plaidAccount['account_id'],
                'name' => $plaidAccount['name'],
                'type' => $type,
                'current_balance' => $plaidAccount['balances']['current'] ?? 0,
                'available_balance' => $plaidAccount['balances']['available'] ?? 0,
            ]);
        }

        $this->connecting = false;
        $this->linkToken = null;
        $this->dispatch('plaid-connected');
    }

    public function render()
    {
        return view('livewire.plaid.plaid-link');
    }
}
```

- [ ] **Step 4: Create the Blade template**

```blade
{{-- steward/resources/views/livewire/plaid/plaid-link.blade.php --}}
<div>
    @if ($error)
        <div class="rounded-lg bg-red-900/50 border border-red-800 p-4 mb-4">
            <p class="text-red-300 text-sm">{{ $error }}</p>
        </div>
    @endif

    @if ($connecting)
        <div class="flex items-center gap-3 text-gray-400">
            <x-lucide-loader-2 class="w-5 h-5 animate-spin" />
            <span>Connecting your bank account...</span>
        </div>
    @else
        <button
            wire:click="createLinkToken"
            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors"
        >
            <x-lucide-link class="w-4 h-4" />
            Connect Bank Account
        </button>
    @endif

    @if ($linkToken)
        <script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
        <script>
            const handler = Plaid.create({
                token: '{{ $linkToken }}',
                onSuccess: (publicToken, metadata) => {
                    @this.call('onSuccess', publicToken, metadata);
                },
                onExit: (err) => {
                    if (err) {
                        console.error('Plaid Link error:', err);
                    }
                },
            });
            handler.open();
        </script>
    @endif
</div>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="PlaidLinkTest"
```

Expected: All 2 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add steward/app/Livewire/Plaid/ steward/resources/views/livewire/plaid/ steward/tests/Feature/Livewire/PlaidLinkTest.php
git commit -m "feat: add PlaidLink Livewire component for bank account onboarding"
```

---

### Task 11: Accounts Page

**Files:**
- Create: `steward/app/Livewire/Accounts/AccountList.php`
- Create: `steward/resources/views/livewire/accounts/account-list.blade.php`
- Modify: `steward/resources/views/pages/accounts.blade.php`
- Test: `steward/tests/Feature/Livewire/AccountListTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/AccountListTest.php
namespace Tests\Feature\Livewire;

use App\Livewire\Accounts\AccountList;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountListTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_accounts(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'name' => 'Checking',
            'current_balance' => 1247.33,
        ]);

        Livewire::actingAs($user)
            ->test(AccountList::class)
            ->assertSee('Checking')
            ->assertSee('1,247.33');
    }

    public function test_shows_plaid_link_when_no_connections(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AccountList::class)
            ->assertSee('Connect Bank Account');
    }

    public function test_refreshes_on_plaid_connected_event(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AccountList::class)
            ->dispatch('plaid-connected')
            ->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="AccountListTest"
```

Expected: FAIL.

- [ ] **Step 3: Implement AccountList component**

```php
<?php
// steward/app/Livewire/Accounts/AccountList.php
namespace App\Livewire\Accounts;

use App\Models\Account;
use App\Models\PlaidConnection;
use Livewire\Attributes\On;
use Livewire\Component;

class AccountList extends Component
{
    #[On('plaid-connected')]
    public function refresh(): void
    {
        // Livewire re-renders automatically
    }

    public function render()
    {
        return view('livewire.accounts.account-list', [
            'connections' => PlaidConnection::with('accounts')->get(),
            'accounts' => Account::all(),
        ]);
    }
}
```

- [ ] **Step 4: Create the Blade template**

```blade
{{-- steward/resources/views/livewire/accounts/account-list.blade.php --}}
<div>
    @if ($connections->isEmpty())
        <div class="rounded-xl border border-gray-800 bg-gray-900 p-8 text-center">
            <x-lucide-landmark class="w-12 h-12 text-gray-600 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-300 mb-2">No bank accounts connected</h3>
            <p class="text-gray-500 text-sm mb-6">Connect your bank to start tracking transactions.</p>
            <livewire:plaid.plaid-link />
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            @foreach ($accounts as $account)
                <div class="rounded-xl border border-gray-800 bg-gray-900 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            @if ($account->type->value === 'checking')
                                <x-lucide-wallet class="w-5 h-5 text-emerald-400" />
                            @else
                                <x-lucide-piggy-bank class="w-5 h-5 text-blue-400" />
                            @endif
                            <div>
                                <h3 class="font-medium text-gray-200">{{ $account->name }}</h3>
                                <p class="text-xs text-gray-500 capitalize">{{ $account->type->value }}</p>
                            </div>
                        </div>
                        @if ($account->last_synced_at)
                            <span class="text-xs text-gray-500">Synced {{ $account->last_synced_at->diffForHumans() }}</span>
                        @endif
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-gray-400">Current Balance</span>
                            <span class="text-2xl font-semibold text-gray-100">${{ number_format($account->current_balance, 2) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-gray-400">Available</span>
                            <span class="text-lg text-gray-300">${{ number_format($account->available_balance, 2) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Connection status --}}
        @foreach ($connections as $connection)
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-4 flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <x-lucide-building-2 class="w-5 h-5 text-gray-400" />
                    <div>
                        <span class="text-sm font-medium text-gray-300">{{ $connection->institution_name }}</span>
                        <span class="text-xs text-gray-500 ml-2">{{ $connection->accounts->count() }} accounts</span>
                    </div>
                </div>
                <span @class([
                    'inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full',
                    'bg-emerald-900/50 text-emerald-400' => $connection->status->value === 'active',
                    'bg-red-900/50 text-red-400' => $connection->status->value === 'error',
                    'bg-yellow-900/50 text-yellow-400' => $connection->status->value === 'needs_reauth',
                ])>
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                    {{ ucfirst(str_replace('_', ' ', $connection->status->value)) }}
                </span>
            </div>
        @endforeach

        <div class="mt-6">
            <livewire:plaid.plaid-link />
        </div>
    @endif
</div>
```

- [ ] **Step 5: Update accounts page to use the component**

```blade
{{-- steward/resources/views/pages/accounts.blade.php --}}
<x-layouts.app title="Accounts">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold">Accounts</h1>
        <p class="text-gray-400 mt-1 text-sm">Manage your connected bank accounts.</p>
    </div>

    <livewire:accounts.account-list />
</x-layouts.app>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="AccountListTest"
```

Expected: All 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Livewire/Accounts/ steward/resources/views/livewire/accounts/ steward/resources/views/pages/accounts.blade.php steward/tests/Feature/Livewire/AccountListTest.php
git commit -m "feat: add Accounts page with balance cards and Plaid connection status"
```

---

### Task 12: Transactions Page

**Files:**
- Create: `steward/app/Livewire/Transactions/TransactionList.php`
- Create: `steward/resources/views/livewire/transactions/transaction-list.blade.php`
- Modify: `steward/resources/views/pages/transactions.blade.php`
- Test: `steward/tests/Feature/Livewire/TransactionListTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/TransactionListTest.php
namespace Tests\Feature\Livewire;

use App\Livewire\Transactions\TransactionList;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['name' => 'Checking']);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Starbucks',
            'amount' => 5.40,
        ]);

        Livewire::actingAs($user)
            ->test(TransactionList::class)
            ->assertSee('Starbucks')
            ->assertSee('5.40');
    }

    public function test_filters_by_account(): void
    {
        $user = User::factory()->create();
        $checking = Account::factory()->create(['name' => 'Checking']);
        $savings = Account::factory()->create(['name' => 'Savings']);

        Transaction::factory()->create(['account_id' => $checking->id, 'merchant_name' => 'Store A']);
        Transaction::factory()->create(['account_id' => $savings->id, 'merchant_name' => 'Store B']);

        Livewire::actingAs($user)
            ->test(TransactionList::class)
            ->set('accountFilter', $checking->id)
            ->assertSee('Store A')
            ->assertDontSee('Store B');
    }

    public function test_filters_by_needs_review(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();

        Transaction::factory()->create(['account_id' => $account->id, 'merchant_name' => 'Reviewed', 'needs_review' => false]);
        Transaction::factory()->create(['account_id' => $account->id, 'merchant_name' => 'Flagged', 'needs_review' => true]);

        Livewire::actingAs($user)
            ->test(TransactionList::class)
            ->set('reviewFilter', true)
            ->assertSee('Flagged')
            ->assertDontSee('Reviewed');
    }

    public function test_can_assign_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $category = Category::factory()->create(['name' => 'Coffee', 'default_bucket' => 'wants']);
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'needs_review' => true,
            'category_id' => null,
        ]);

        Livewire::actingAs($user)
            ->test(TransactionList::class)
            ->call('assignCategory', $transaction->id, $category->id);

        $transaction->refresh();
        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertEquals('wants', $transaction->budget_bucket->value);
        $this->assertFalse($transaction->needs_review);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="TransactionListTest"
```

Expected: FAIL.

- [ ] **Step 3: Implement TransactionList component**

```php
<?php
// steward/app/Livewire/Transactions/TransactionList.php
namespace App\Livewire\Transactions;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use WithPagination;

    public ?int $accountFilter = null;
    public ?int $categoryFilter = null;
    public ?bool $reviewFilter = null;
    public string $search = '';
    public string $sortField = 'date';
    public string $sortDirection = 'desc';

    protected $queryString = [
        'accountFilter' => ['as' => 'account'],
        'categoryFilter' => ['as' => 'category'],
        'reviewFilter' => ['as' => 'review'],
        'search' => ['as' => 'q'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAccountFilter(): void
    {
        $this->resetPage();
    }

    public function assignCategory(int $transactionId, int $categoryId): void
    {
        $transaction = Transaction::findOrFail($transactionId);
        $category = Category::findOrFail($categoryId);

        $transaction->update([
            'category_id' => $category->id,
            'budget_bucket' => $category->default_bucket,
            'needs_review' => false,
            'categorization_confidence' => 1.00,
        ]);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function render()
    {
        $query = Transaction::with(['account', 'category']);

        if ($this->accountFilter) {
            $query->where('account_id', $this->accountFilter);
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        if ($this->reviewFilter !== null) {
            $query->where('needs_review', $this->reviewFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('merchant_name', 'ilike', "%{$this->search}%")
                  ->orWhere('description', 'ilike', "%{$this->search}%");
            });
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        return view('livewire.transactions.transaction-list', [
            'transactions' => $query->paginate(25),
            'accounts' => Account::all(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Create the Blade template**

```blade
{{-- steward/resources/views/livewire/transactions/transaction-list.blade.php --}}
<div>
    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <div class="flex-1 min-w-[200px]">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search transactions..."
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-blue-500"
            />
        </div>

        <select wire:model.live="accountFilter" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200">
            <option value="">All Accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="categoryFilter" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="reviewFilter" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200">
            <option value="">All Status</option>
            <option value="1">Needs Review</option>
            <option value="0">Reviewed</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800 text-gray-400 text-left">
                    <th class="px-4 py-3 font-medium cursor-pointer hover:text-gray-200" wire:click="sortBy('date')">
                        <span class="flex items-center gap-1">
                            Date
                            @if ($sortField === 'date')
                                <x-lucide-chevron-down @class(['w-4 h-4', 'rotate-180' => $sortDirection === 'asc']) />
                            @endif
                        </span>
                    </th>
                    <th class="px-4 py-3 font-medium">Merchant</th>
                    <th class="px-4 py-3 font-medium">Description</th>
                    <th class="px-4 py-3 font-medium">Account</th>
                    <th class="px-4 py-3 font-medium">Category</th>
                    <th class="px-4 py-3 font-medium text-right cursor-pointer hover:text-gray-200" wire:click="sortBy('amount')">
                        <span class="flex items-center justify-end gap-1">
                            Amount
                            @if ($sortField === 'amount')
                                <x-lucide-chevron-down @class(['w-4 h-4', 'rotate-180' => $sortDirection === 'asc']) />
                            @endif
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/50">
                @forelse ($transactions as $txn)
                    <tr @class(['hover:bg-gray-800/30', 'bg-amber-950/20' => $txn->needs_review])>
                        <td class="px-4 py-3 text-gray-300 whitespace-nowrap">{{ $txn->date->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-gray-200 font-medium">{{ $txn->merchant_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $txn->description }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $txn->account->name }}</td>
                        <td class="px-4 py-3">
                            @if ($txn->needs_review)
                                <select
                                    wire:change="assignCategory({{ $txn->id }}, $event.target.value)"
                                    class="bg-gray-800 border border-amber-700 rounded px-2 py-1 text-xs text-gray-200"
                                >
                                    <option value="">Assign category...</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            @elseif ($txn->category)
                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-300">
                                    <x-dynamic-component :component="'lucide-' . $txn->category->icon" class="w-3.5 h-3.5" />
                                    {{ $txn->category->name }}
                                </span>
                            @else
                                <span class="text-gray-500 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-gray-200">${{ number_format($txn->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                            No transactions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $transactions->links() }}
    </div>
</div>
```

- [ ] **Step 5: Update transactions page**

```blade
{{-- steward/resources/views/pages/transactions.blade.php --}}
<x-layouts.app title="Transactions">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold">Transactions</h1>
        <p class="text-gray-400 mt-1 text-sm">View, filter, and categorize your transactions.</p>
    </div>

    <livewire:transactions.transaction-list />
</x-layouts.app>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="TransactionListTest"
```

Expected: All 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Livewire/Transactions/ steward/resources/views/livewire/transactions/ steward/resources/views/pages/transactions.blade.php steward/tests/Feature/Livewire/TransactionListTest.php
git commit -m "feat: add Transactions page with filters, sorting, and inline category assignment"
```

---

### Task 13: Settings — Income Sources

**Files:**
- Create: `steward/app/Livewire/Settings/IncomeSources.php`
- Create: `steward/resources/views/livewire/settings/income-sources.blade.php`
- Create: `steward/database/factories/IncomeSourceFactory.php`
- Test: `steward/tests/Feature/Livewire/IncomeSourcesTest.php`

- [ ] **Step 1: Create IncomeSource factory**

```php
<?php
// steward/database/factories/IncomeSourceFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class IncomeSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Main Job', 'Side Gig', 'Church']),
            'amount' => $this->faker->randomFloat(2, 500, 5000),
            'frequency' => $this->faker->randomElement(['weekly', 'biweekly', 'monthly']),
            'next_pay_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 2: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/IncomeSourcesTest.php
namespace Tests\Feature\Livewire;

use App\Livewire\Settings\IncomeSources;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncomeSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_existing_income_sources(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['name' => 'Main Job', 'amount' => 2400]);

        Livewire::actingAs($user)
            ->test(IncomeSources::class)
            ->assertSee('Main Job')
            ->assertSee('2,400.00');
    }

    public function test_can_add_income_source(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(IncomeSources::class)
            ->set('name', 'Church')
            ->set('amount', 700)
            ->set('frequency', 'biweekly')
            ->set('nextPayDate', '2026-05-09')
            ->call('save');

        $this->assertDatabaseHas('income_sources', [
            'name' => 'Church',
            'amount' => 700,
            'frequency' => 'biweekly',
        ]);
    }

    public function test_can_delete_income_source(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::factory()->create();

        Livewire::actingAs($user)
            ->test(IncomeSources::class)
            ->call('delete', $source->id);

        $this->assertDatabaseMissing('income_sources', ['id' => $source->id]);
    }

    public function test_shows_total_monthly_income(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['amount' => 2400, 'frequency' => 'biweekly']);
        IncomeSource::factory()->create(['amount' => 700, 'frequency' => 'biweekly']);

        Livewire::actingAs($user)
            ->test(IncomeSources::class)
            ->assertSee('6,716.67');
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="IncomeSourcesTest"
```

Expected: FAIL.

- [ ] **Step 4: Implement IncomeSources component**

```php
<?php
// steward/app/Livewire/Settings/IncomeSources.php
namespace App\Livewire\Settings;

use App\Models\IncomeSource;
use Livewire\Component;

class IncomeSources extends Component
{
    public string $name = '';
    public string $amount = '';
    public string $frequency = 'biweekly';
    public string $nextPayDate = '';
    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'frequency' => 'required|in:weekly,biweekly,monthly',
            'nextPayDate' => 'required|date',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            $source = IncomeSource::findOrFail($this->editingId);
            $source->update([
                'name' => $this->name,
                'amount' => $this->amount,
                'frequency' => $this->frequency,
                'next_pay_date' => $this->nextPayDate,
            ]);
        } else {
            IncomeSource::create([
                'name' => $this->name,
                'amount' => $this->amount,
                'frequency' => $this->frequency,
                'next_pay_date' => $this->nextPayDate,
            ]);
        }

        $this->reset(['name', 'amount', 'frequency', 'nextPayDate', 'editingId']);
        $this->frequency = 'biweekly';
    }

    public function edit(int $id): void
    {
        $source = IncomeSource::findOrFail($id);
        $this->editingId = $source->id;
        $this->name = $source->name;
        $this->amount = (string) $source->amount;
        $this->frequency = $source->frequency;
        $this->nextPayDate = $source->next_pay_date->format('Y-m-d');
    }

    public function delete(int $id): void
    {
        IncomeSource::findOrFail($id)->delete();
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'amount', 'frequency', 'nextPayDate', 'editingId']);
        $this->frequency = 'biweekly';
    }

    public function render()
    {
        $sources = IncomeSource::where('is_active', true)->get();
        $totalMonthly = $sources->sum(fn ($s) => $s->monthlyAmount());

        return view('livewire.settings.income-sources', [
            'sources' => $sources,
            'totalMonthly' => $totalMonthly,
        ]);
    }
}
```

- [ ] **Step 5: Create the Blade template**

```blade
{{-- steward/resources/views/livewire/settings/income-sources.blade.php --}}
<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-200">Income Sources</h2>
            <p class="text-sm text-gray-400">Total monthly income: <span class="text-white font-medium">${{ number_format($totalMonthly, 2) }}</span></p>
        </div>
    </div>

    {{-- Existing sources --}}
    @if ($sources->isNotEmpty())
        <div class="space-y-3 mb-8">
            @foreach ($sources as $source)
                <div class="flex items-center justify-between rounded-lg border border-gray-800 bg-gray-900 p-4">
                    <div class="flex items-center gap-4">
                        <x-lucide-banknote class="w-5 h-5 text-emerald-400" />
                        <div>
                            <span class="font-medium text-gray-200">{{ $source->name }}</span>
                            <span class="text-gray-500 text-sm ml-2">${{ number_format($source->amount, 2) }} / {{ $source->frequency }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-400">Next: {{ $source->next_pay_date->format('M j, Y') }}</span>
                        <span class="text-sm text-gray-500">${{ number_format($source->monthlyAmount(), 2) }}/mo</span>
                        <button wire:click="edit({{ $source->id }})" class="text-gray-400 hover:text-white transition-colors">
                            <x-lucide-pencil class="w-4 h-4" />
                        </button>
                        <button wire:click="delete({{ $source->id }})" wire:confirm="Remove this income source?" class="text-gray-400 hover:text-red-400 transition-colors">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add/Edit form --}}
    <form wire:submit="save" class="rounded-lg border border-gray-800 bg-gray-900 p-6">
        <h3 class="text-sm font-medium text-gray-300 mb-4">
            {{ $editingId ? 'Edit Income Source' : 'Add Income Source' }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Name</label>
                <input type="text" wire:model="name" placeholder="Main Job"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-blue-500" />
                @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Amount</label>
                <input type="number" step="0.01" wire:model="amount" placeholder="2400.00"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-blue-500" />
                @error('amount') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Frequency</label>
                <select wire:model="frequency"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200">
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Bi-weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Next Pay Date</label>
                <input type="date" wire:model="nextPayDate"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-blue-500" />
                @error('nextPayDate') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex items-center gap-3 mt-4">
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">
                {{ $editingId ? 'Update' : 'Add Income Source' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-gray-400 hover:text-white text-sm transition-colors">
                    Cancel
                </button>
            @endif
        </div>
    </form>
</div>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="IncomeSourcesTest"
```

Expected: All 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Livewire/Settings/IncomeSources.php steward/resources/views/livewire/settings/income-sources.blade.php steward/database/factories/IncomeSourceFactory.php steward/tests/Feature/Livewire/IncomeSourcesTest.php
git commit -m "feat: add Income Sources settings with CRUD and monthly total calculation"
```

---

### Task 14: Settings — Sync Schedule

**Files:**
- Create: `steward/app/Livewire/Settings/SyncSchedule.php`
- Create: `steward/resources/views/livewire/settings/sync-schedule.blade.php`
- Modify: `steward/resources/views/pages/settings.blade.php`
- Test: `steward/tests/Feature/Livewire/SyncScheduleTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// steward/tests/Feature/Livewire/SyncScheduleTest.php
namespace Tests\Feature\Livewire;

use App\Livewire\Settings\SyncSchedule;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_current_schedule(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('sync_schedule', '0 4 * * *');

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->assertSet('hour', '4')
            ->assertSet('minute', '0');
    }

    public function test_can_update_schedule(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('sync_schedule', '0 4 * * *');

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->set('hour', '6')
            ->set('minute', '30')
            ->call('save');

        $this->assertEquals('30 6 * * *', AppSetting::getValue('sync_schedule'));
    }

    public function test_displays_confidence_threshold(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('categorization_confidence_threshold', 0.9);

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->assertSet('confidenceThreshold', '0.9');
    }

    public function test_can_update_confidence_threshold(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('categorization_confidence_threshold', 0.9);

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->set('confidenceThreshold', '0.75')
            ->call('save');

        $this->assertEquals(0.75, AppSetting::getValue('categorization_confidence_threshold'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --filter="SyncScheduleTest"
```

Expected: FAIL.

- [ ] **Step 3: Implement SyncSchedule component**

```php
<?php
// steward/app/Livewire/Settings/SyncSchedule.php
namespace App\Livewire\Settings;

use App\Models\AppSetting;
use Livewire\Component;

class SyncSchedule extends Component
{
    public string $hour = '4';
    public string $minute = '0';
    public string $confidenceThreshold = '0.9';
    public bool $saved = false;

    public function mount(): void
    {
        $cron = AppSetting::getValue('sync_schedule', '0 4 * * *');
        $parts = explode(' ', $cron);
        $this->minute = $parts[0] ?? '0';
        $this->hour = $parts[1] ?? '4';

        $this->confidenceThreshold = (string) AppSetting::getValue('categorization_confidence_threshold', 0.9);
    }

    public function save(): void
    {
        $this->validate([
            'hour' => 'required|integer|min:0|max:23',
            'minute' => 'required|integer|min:0|max:59',
            'confidenceThreshold' => 'required|numeric|min:0.5|max:1.0',
        ]);

        AppSetting::setValue('sync_schedule', "{$this->minute} {$this->hour} * * *");
        AppSetting::setValue('categorization_confidence_threshold', (float) $this->confidenceThreshold);

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.settings.sync-schedule');
    }
}
```

- [ ] **Step 4: Create the Blade template**

```blade
{{-- steward/resources/views/livewire/settings/sync-schedule.blade.php --}}
<div>
    <h2 class="text-lg font-semibold text-gray-200 mb-6">Sync & AI Settings</h2>

    <form wire:submit="save" class="space-y-8">
        {{-- Sync Schedule --}}
        <div class="rounded-lg border border-gray-800 bg-gray-900 p-6">
            <h3 class="text-sm font-medium text-gray-300 mb-4 flex items-center gap-2">
                <x-lucide-clock class="w-4 h-4 text-gray-400" />
                Daily Sync Schedule
            </h3>
            <p class="text-xs text-gray-500 mb-4">When should we fetch new transactions from your bank?</p>

            <div class="flex items-center gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Hour</label>
                    <select wire:model="hour" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200">
                        @for ($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}">{{ sprintf('%02d', $h) }}:00</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Minute</label>
                    <select wire:model="minute" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200">
                        @foreach ([0, 15, 30, 45] as $m)
                            <option value="{{ $m }}">:{{ sprintf('%02d', $m) }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-sm text-gray-400 self-end pb-2">
                    Sync runs daily at {{ sprintf('%02d', $hour) }}:{{ sprintf('%02d', $minute) }}
                </p>
            </div>
        </div>

        {{-- Confidence Threshold --}}
        <div class="rounded-lg border border-gray-800 bg-gray-900 p-6">
            <h3 class="text-sm font-medium text-gray-300 mb-4 flex items-center gap-2">
                <x-lucide-brain class="w-4 h-4 text-gray-400" />
                Categorization Confidence Threshold
            </h3>
            <p class="text-xs text-gray-500 mb-4">Transactions categorized below this confidence level will be flagged for manual review.</p>

            <div class="flex items-center gap-4">
                <input
                    type="range"
                    wire:model.live="confidenceThreshold"
                    min="0.5"
                    max="1.0"
                    step="0.05"
                    class="flex-1 accent-blue-500"
                />
                <span class="text-lg font-mono text-gray-200 w-16 text-right">{{ number_format((float) $confidenceThreshold * 100, 0) }}%</span>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex items-center gap-4">
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">
                Save Settings
            </button>
            @if ($saved)
                <span class="text-emerald-400 text-sm" wire:transition>Settings saved.</span>
            @endif
        </div>
    </form>
</div>
```

- [ ] **Step 5: Update the settings page**

```blade
{{-- steward/resources/views/pages/settings.blade.php --}}
<x-layouts.app title="Settings">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold">Settings</h1>
        <p class="text-gray-400 mt-1 text-sm">Configure your household finances.</p>
    </div>

    <div class="max-w-3xl space-y-12">
        <livewire:settings.income-sources />
        <livewire:settings.sync-schedule />
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --filter="SyncScheduleTest"
```

Expected: All 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add steward/app/Livewire/Settings/ steward/resources/views/livewire/settings/sync-schedule.blade.php steward/resources/views/pages/settings.blade.php steward/tests/Feature/Livewire/SyncScheduleTest.php
git commit -m "feat: add Settings page with sync schedule and confidence threshold controls"
```

---

### Task 15: Scheduler Configuration

**Files:**
- Modify: `steward/routes/console.php`

- [ ] **Step 1: Configure the Laravel scheduler**

```php
<?php
// steward/routes/console.php
use App\Jobs\PlaidSyncJob;
use App\Models\AppSetting;
use App\Models\PlaidConnection;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $connections = PlaidConnection::where('status', 'active')->get();

    foreach ($connections as $connection) {
        PlaidSyncJob::dispatch($connection);
    }
})->cron(AppSetting::getValue('sync_schedule', '0 4 * * *'))
  ->name('plaid-sync')
  ->withoutOverlapping();
```

- [ ] **Step 2: Add scheduler to Docker**

Update the `docker-compose.yml` to add a scheduler service. Add this service after the `worker` service:

```yaml
  scheduler:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    command: ["php", "/var/www/html/artisan", "schedule:work"]
    volumes:
      - ./steward:/var/www/html
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
```

- [ ] **Step 3: Verify scheduler recognizes the command**

```bash
docker compose up -d scheduler
docker compose exec scheduler php artisan schedule:list
```

Expected: Shows the `plaid-sync` scheduled task with the configured cron expression.

- [ ] **Step 4: Commit**

```bash
git add steward/routes/console.php docker-compose.yml
git commit -m "feat: add Laravel scheduler for daily Plaid sync with configurable schedule"
```

---

### Task 16: Run Full Test Suite

- [ ] **Step 1: Run all tests**

```bash
docker compose exec app php artisan test
```

Expected: All tests pass — unit model tests, PlaidService tests, PlaidSyncJob tests, and all Livewire component tests.

- [ ] **Step 2: Verify the full stack manually**

1. Open `http://localhost` and log in with `admin@steward.local` / `password`
2. Verify sidebar navigation works — click through all pages
3. Go to Accounts — verify "Connect Bank Account" button appears
4. Go to Settings — verify Income Sources form and Sync Schedule controls render
5. Go to Transactions — verify empty state shows

- [ ] **Step 3: Final commit if any adjustments were needed**

```bash
docker compose exec app php artisan test
git status
# If clean: no commit needed
# If fixes were applied: stage and commit with descriptive message
```
