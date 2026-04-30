<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@steward.local'],
            ['name' => 'Jamie', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        User::updateOrCreate(
            ['email' => 'member@steward.local'],
            ['name' => 'Wife', 'password' => Hash::make('password'), 'role' => 'member']
        );
    }
}
