<?php

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

        $this->assertInstanceOf(Account::class, $transaction->account);
        $this->assertEquals($account->id, $transaction->account->id);
    }

    public function test_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $this->assertInstanceOf(Category::class, $transaction->category);
        $this->assertEquals($category->id, $transaction->category->id);
    }

    public function test_category_is_nullable(): void
    {
        $transaction = Transaction::factory()->create(['category_id' => null]);

        $this->assertNull($transaction->category);
    }

    public function test_scope_needs_review(): void
    {
        $account = Account::factory()->create();

        Transaction::factory()->count(3)->create([
            'account_id' => $account->id,
            'needs_review' => true,
        ]);

        Transaction::factory()->count(2)->create([
            'account_id' => $account->id,
            'needs_review' => false,
        ]);

        $needsReview = Transaction::where('needs_review', true)->get();

        $this->assertCount(3, $needsReview);
    }
}
