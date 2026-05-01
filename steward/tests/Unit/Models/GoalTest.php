<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_progress_percentage(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 10000,
            'current_amount' => 2500,
        ]);

        $this->assertEquals(25.0, $goal->progressPercent());
    }

    public function test_computes_remaining_amount(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 10000,
            'current_amount' => 2500,
        ]);

        $this->assertEquals(7500.0, $goal->remaining());
    }

    public function test_computes_monthly_savings_needed(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 12000,
            'current_amount' => 0,
            'target_date' => now()->addMonths(12)->toDateString(),
        ]);

        $this->assertEquals(1000.0, $goal->monthlySavingsNeeded());
    }

    public function test_monthly_savings_returns_null_without_target_date(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 12000,
            'current_amount' => 0,
            'target_date' => null,
        ]);

        $this->assertNull($goal->monthlySavingsNeeded());
    }

    public function test_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $goal = Goal::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $goal->category);
        $this->assertEquals($category->id, $goal->category->id);
    }

    public function test_category_is_nullable(): void
    {
        $goal = Goal::factory()->create(['category_id' => null]);

        $this->assertNull($goal->category);
    }
}
