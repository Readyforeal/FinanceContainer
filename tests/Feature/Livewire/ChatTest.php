<?php

namespace Tests\Feature\Livewire;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_new_conversation(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('chat.chat-page');

        $component->call('newConversation');

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $user->id,
        ]);

        $conversation = ChatConversation::where('user_id', $user->id)->first();
        $this->assertNotNull($conversation);

        $component->assertSet('activeConversationId', $conversation->id);
    }

    public function test_can_send_message_and_receive_response(): void
    {
        $user = User::factory()->create();

        $conversation = ChatConversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'New Conversation',
        ]);

        $mock = $this->mock(OllamaService::class);
        $mock->shouldReceive('streamChat')
            ->once()
            ->andReturnUsing(function ($systemPrompt, $messages, $onToken) {
                $onToken('Great ');
                $onToken('question!');
            });

        $component = Livewire::actingAs($user)
            ->test('chat.chat-page', ['activeConversationId' => $conversation->id])
            ->set('messageText', 'How am I doing financially?')
            ->call('sendMessage');

        // sendMessage saves user message and sets isStreaming
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'How am I doing financially?',
        ]);

        $component->assertSet('isStreaming', true);

        // fetchResponse calls Ollama and saves assistant message
        $component->call('fetchResponse');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Great question!',
        ]);

        $component->assertSet('isStreaming', false);
    }

    public function test_displays_conversation_history(): void
    {
        $user = User::factory()->create();

        $conversation = ChatConversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Conversation',
        ]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What is my budget?',
        ]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Your budget looks good.',
        ]);

        Livewire::actingAs($user)
            ->test('chat.chat-page', ['activeConversationId' => $conversation->id])
            ->assertSee('What is my budget?')
            ->assertSee('Your budget looks good.');
    }

    public function test_lists_user_conversations(): void
    {
        $user = User::factory()->create();

        ChatConversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Budget Planning',
        ]);

        ChatConversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Savings Goals',
        ]);

        Livewire::actingAs($user)
            ->test('chat.chat-page')
            ->assertSee('Budget Planning')
            ->assertSee('Savings Goals');
    }
}
