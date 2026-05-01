<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Emergency Fund',
                'New Car',
                'Roof Repair',
                'Family Vacation',
                'Kitchen Renovation',
            ]),
            'target_amount' => $this->faker->randomFloat(2, 1000, 30000),
            'current_amount' => $this->faker->randomFloat(2, 0, 5000),
            'target_date' => $this->faker->dateTimeBetween('+3 months', '+3 years')->format('Y-m-d'),
            'priority' => $this->faker->randomElement(['high', 'medium', 'low']),
            'bucket' => null,
            'category_id' => null,
            'notes' => null,
            'is_completed' => false,
        ];
    }
}
