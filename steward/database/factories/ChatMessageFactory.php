<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant']),
            'content' => $this->faker->sentence(),
        ];
    }
}
