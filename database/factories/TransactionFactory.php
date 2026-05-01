<?php

namespace Database\Factories;

use App\Models\Account;
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
