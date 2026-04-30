<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
            'period_start' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'period_end' => $this->faker->dateTimeBetween('now', '+1 day')->format('Y-m-d'),
            'total_income' => $this->faker->randomFloat(2, 0, 5000),
            'total_spent' => $this->faker->randomFloat(2, 0, 3000),
            'needs_spent' => $this->faker->randomFloat(2, 0, 1500),
            'wants_spent' => $this->faker->randomFloat(2, 0, 900),
            'savings_spent' => $this->faker->randomFloat(2, 0, 600),
            'ai_analysis' => $this->faker->sentence(),
            'ai_advice' => null,
            'habit_flags' => null,
        ];
    }
}
