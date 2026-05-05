# Bill Management & Calendar System Design

## Overview

Add bill tracking with automatic payment detection, calendar visualization, and AI integration for proactive financial coaching. Bills are linked to transaction merchants via pattern matching so the system auto-detects when a bill is paid.

## Data Model

### `bills` table

| Column | Type | Description |
|---|---|---|
| id | bigint PK | |
| name | string | Bill name (e.g., "Electric") |
| payee | string | Who you pay (e.g., "Black Hills Energy") |
| merchant_pattern | string | Partial match string for transaction matching (e.g., `BLACK HILLS ENRG`) |
| amount | decimal(10,2), nullable | Expected amount (null if variable) |
| is_fixed | boolean, default false | Whether amount is fixed or fluctuates |
| due_day | integer | Day of month (1-31) when bill is due |
| frequency | string | Enum: monthly, quarterly, semi_annually, annually, weekly, biweekly |
| is_autopay | boolean, default false | Whether bill is on auto-pay |
| account_id | foreignId | Which account it's paid from |
| category_id | foreignId, nullable | Links to existing spending category |
| notes | text, nullable | Optional notes |
| is_active | boolean, default true | Soft disable without deleting |
| timestamps | | |

### Bill Frequency Enum

`App\Enums\BillFrequency`: monthly, quarterly, semi_annually, annually, weekly, biweekly

### No Payment Table

Payments are detected by matching transactions against `merchant_pattern` using a case-insensitive partial match (ILIKE/LIKE). The system queries for transactions in the current billing period with a merchant name containing the pattern. If found, the bill is considered paid.

## Bill Status Logic

For any billing period, a bill's status is determined by:

- **Paid** -- a transaction matching `merchant_pattern` exists in the current billing period
- **Overdue** -- due date has passed, no matching transaction found
- **Due Soon** -- within 5 days of due date, no matching transaction
- **Upcoming** -- due date is more than 5 days away, no matching transaction

## UI Components

### Bills Page (`/bills`)

New page accessible from sidebar (desktop) and dock grid (mobile). Contains two sections:

#### 1. Calendar View

Month calendar grid showing:
- Bills plotted on their due dates, color-coded by status (green=paid, red=overdue, amber=due soon, zinc=upcoming)
- Paydays plotted from Income Sources data (blue markers)
- Month navigation (previous/next arrows, same pattern as budgets page)
- Tapping a day shows that day's bills in a detail view or modal

#### 2. Upcoming Bills List

Timeline of bills due in the next 30 days:
- Each bill shows: name, payee, expected amount, due date, status badge
- Paid bills show actual transaction amount vs expected amount
- Sorted by due date ascending
- Overdue bills pinned to top with red styling

### Bill Editor Modal

Same pattern as budget/category/transaction modals (flux:modal, w-full md:w-2xl):
- Name, Payee, Merchant Pattern inputs
- Amount input (disabled/hidden when is_fixed is false)
- Fixed/Variable toggle (flux:switch)
- Due Day select (1-31)
- Frequency select
- Auto-pay toggle (flux:switch)
- Account select
- Category select (optional)
- Notes textarea
- Cancel / Save buttons justified between
- Delete button full-width below when editing

### Navigation

- Add to desktop sidebar nav items
- Add to mobile dock expanded grid
- Dock action button dispatches for Add Bill on the bills page

## AI Integration

### FinancialContextBuilder

Add `buildBillsSection()` method to dynamic context that provides:

```
BILLS (current month):
  - Electric: $~120, due 15th, UNPAID (due in 3 days)
  - Mortgage: $945.27, due 1st, PAID on Apr 1 ($945.27)
  - Car Insurance: $89.88, due 7th, PAID on Apr 7 ($89.88)
  Total upcoming unpaid: $320.00
  Available balance: $421.81
```

This gives the AI full context to make coaching recommendations like:
- "Your electric bill is due in 3 days, pay it now while you have the balance"
- "After paying electric, throw $50 into savings before the weekend"
- "Don't spend for a couple days -- car insurance comes out on the 7th"

## Merchant Pattern Matching

Bills are matched to transactions using case-insensitive partial matching:

```php
Transaction::where('merchant_name', 'ilike', '%' . $bill->merchant_pattern . '%')
    ->whereBetween('date', [$periodStart, $periodEnd])
    ->where('amount', '<', 0)
    ->first();
```

The billing period is calculated based on the bill's frequency:
- Monthly: from the 1st of the current month to end of month
- Quarterly: 3-month window ending on the due date month
- Annually: current calendar year
- Weekly/Biweekly: rolling window based on frequency

## Model Relationships

```php
class Bill extends Model
{
    public function account(): BelongsTo
    public function category(): BelongsTo
    
    // Calculate status for a given date/period
    public function statusFor(Carbon $date): string
    
    // Find matching transaction for current period
    public function matchingTransaction(): ?Transaction
    
    // Next due date from a given date
    public function nextDueDate(Carbon $from = null): Carbon
}
```
