<?php

namespace Database\Factories;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Checking', 'Savings']),
            'type' => $this->faker->randomElement(AccountType::cases()),
            'current_balance' => $this->faker->randomFloat(2, 100, 10000),
            'available_balance' => $this->faker->randomFloat(2, 100, 10000),
            'last_synced_at' => null,
        ];
    }
}
