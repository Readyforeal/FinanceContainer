<?php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Mortgage', 'icon' => 'home', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Electric', 'icon' => 'zap', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Gas Utility', 'icon' => 'flame', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Water', 'icon' => 'droplets', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Internet', 'icon' => 'wifi', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Phone', 'icon' => 'smartphone', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Groceries', 'icon' => 'shopping-cart', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Gasoline', 'icon' => 'fuel', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Car Insurance', 'icon' => 'shield', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Health Insurance', 'icon' => 'heart-pulse', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Car Maintenance', 'icon' => 'wrench', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Home Repair', 'icon' => 'hammer', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Medical', 'icon' => 'stethoscope', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Childcare', 'icon' => 'baby', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Dining Out', 'icon' => 'utensils', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Coffee', 'icon' => 'coffee', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Entertainment', 'icon' => 'film', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Subscriptions', 'icon' => 'repeat', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Shopping', 'icon' => 'shopping-bag', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Hobbies', 'icon' => 'puzzle', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Clothing', 'icon' => 'shirt', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Personal Care', 'icon' => 'sparkles', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Gifts', 'icon' => 'gift', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Emergency Fund', 'icon' => 'piggy-bank', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Car Fund', 'icon' => 'car', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Renovation Fund', 'icon' => 'paint-roller', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Tithe', 'icon' => 'church', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Debt Payment', 'icon' => 'credit-card', 'default_bucket' => 'needs', 'is_essential' => true, 'is_system' => true],
            ['name' => 'Income', 'icon' => 'banknote', 'default_bucket' => 'income', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Savings Deposit', 'icon' => 'arrow-down-to-line', 'default_bucket' => 'savings', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Savings Withdrawal', 'icon' => 'arrow-up-from-line', 'default_bucket' => 'transfer', 'is_essential' => false, 'is_system' => true],
            ['name' => 'Uncategorized', 'icon' => 'circle-alert', 'default_bucket' => 'wants', 'is_essential' => false, 'is_system' => true],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
