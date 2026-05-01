<?php

namespace Tests\Feature\Livewire;

use App\Enums\BudgetBucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_categories_with_icons(): void
    {
        $user = User::factory()->create();

        Category::factory()->create([
            'name' => 'Housing',
            'icon' => 'home',
            'default_bucket' => BudgetBucket::Needs,
        ]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->assertSee('Housing')
            ->assertSee('needs');
    }

    public function test_can_create_category(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->set('name', 'Coffee Shops')
            ->set('icon', 'coffee')
            ->set('defaultBucket', 'wants')
            ->set('isEssential', false)
            ->call('save')
            ->assertSet('name', '');

        $this->assertDatabaseHas('categories', [
            'name' => 'Coffee Shops',
            'icon' => 'coffee',
            'default_bucket' => 'wants',
            'is_system' => false,
        ]);
    }

    public function test_can_update_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'Old Name',
            'is_system' => false,
        ]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->call('edit', $category->id)
            ->set('name', 'New Name')
            ->call('save')
            ->assertSet('editingId', null);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_delete_non_system_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'To Delete',
            'is_system' => false,
        ]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->call('delete', $category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_system_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'System Category',
            'is_system' => true,
        ]);

        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->call('delete', $category->id);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_shows_average_spend(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'Dining Out',
            'default_bucket' => BudgetBucket::Wants,
        ]);

        // Create transactions over the last 3 months
        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => 300,
            'date' => now()->subMonth(),
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => 300,
            'date' => now(),
        ]);

        // $600 / 3 months = $200 average
        Livewire::actingAs($user)
            ->test('categories.category-manager')
            ->assertSee('200');
    }
}
