<?php

namespace Database\Factories;

use App\Enums\BillFrequency;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Bill',
            'payee' => $this->faker->company(),
            'merchant_pattern' => strtoupper($this->faker->word()),
            'amount' => $this->faker->randomFloat(2, 20, 500),
            'is_fixed' => $this->faker->boolean(70),
            'due_day' => $this->faker->numberBetween(1, 28),
            'frequency' => $this->faker->randomElement(BillFrequency::cases()),
            'is_autopay' => $this->faker->boolean(40),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'is_active' => true,
        ];
    }
}
