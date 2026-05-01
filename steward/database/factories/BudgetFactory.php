<?php

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
            'month' => now()->subMonths($this->faker->numberBetween(0, 11))->format('Y-m'),
            'budgeted_amount' => $this->faker->randomFloat(2, 50, 2000),
            'bucket' => $this->faker->randomElement(BudgetBucket::cases()),
        ];
    }
}
