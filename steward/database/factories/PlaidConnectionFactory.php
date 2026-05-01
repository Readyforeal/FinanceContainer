<?php

namespace Database\Factories;

use App\Enums\PlaidConnectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaidConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'access_token' => $this->faker->sha256(),
            'item_id' => 'item_' . $this->faker->unique()->bothify('####????'),
            'institution_name' => $this->faker->company(),
            'cursor' => null,
            'status' => PlaidConnectionStatus::Active,
        ];
    }
}
