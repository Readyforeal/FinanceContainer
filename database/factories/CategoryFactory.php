<?php

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
