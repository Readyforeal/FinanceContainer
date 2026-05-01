<?php

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
