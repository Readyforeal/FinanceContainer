<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Livewire\Component;

new class extends Component {
    public ?int $activeConversationId = null;
    public string $messageText = '';
    public string $streamingResponse = '';
    public bool $isStreaming = false;

    public function mount(?int $activeConversationId = null): void
    {
        if ($activeConversationId !== null) {
            $this->activeConversationId = $activeConversationId;
        } else {
            $latest = ChatConversation::where('user_id', auth()->id())
                ->latest()
                ->first();

            $this->activeConversationId = $latest?->id;
        }
    }

    public function newConversation(): void
    {
        $conversation = ChatConversation::create([
            'user_id' => auth()->id(),
            'title' => 'New Conversation',
        ]);

        $this->activeConversationId = $conversation->id;
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
    }

    public function sendMessage(): void
    {
        if (blank($this->messageText) || $this->isStreaming || $this->activeConversationId === null) {
            return;
        }

        $text = $this->messageText;
        $this->messageText = '';

        // Create the user message
        ChatMessage::create([
            'conversation_id' => $this->activeConversationId,
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $text,
        ]);

        // Update title if still default
        $conversation = ChatConversation::find($this->activeConversationId);
        if ($conversation && $conversation->title === 'New Conversation') {
            $conversation->update(['title' => \Illuminate\Support\Str::limit($text, 60)]);
        }

        // Build conversation history for Ollama
        $history = ChatMessage::where('conversation_id', $this->activeConversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();

        // Build prompts using financial context
        $contextBuilder = app(FinancialContextBuilder::class);
        $systemPrompt = $contextBuilder->buildSystemPrompt() . "\n\n" . $contextBuilder->buildDynamicContext();

        // Stream response from Ollama
        $this->isStreaming = true;
        $this->streamingResponse = '';

        $fullResponse = '';

        try {
            $ollama = app(OllamaService::class);
            $ollama->streamChat($systemPrompt, $history, function (string $token) use (&$fullResponse): void {
                $fullResponse .= $token;
            });
        } catch (\Throwable $e) {
            $fullResponse = "I'm unable to connect to the AI service right now. Please try again later.\n\nError: " . $e->getMessage();
        }

        $this->isStreaming = false;
        $this->streamingResponse = '';

        // Persist the assistant message
        ChatMessage::create([
            'conversation_id' => $this->activeConversationId,
            'user_id' => auth()->id(),
            'role' => 'assistant',
            'content' => $fullResponse,
        ]);
    }

    public function with(): array
    {
        $conversations = ChatConversation::where('user_id', auth()->id())
            ->latest()
            ->get();

        $messages = $this->activeConversationId
            ? ChatMessage::where('conversation_id', $this->activeConversationId)
                ->orderBy('created_at')
                ->get()
            : collect();

        return [
            'conversations' => $conversations,
            'messages' => $messages,
        ];
    }
};
?>

<div class="flex h-[calc(100vh-8rem)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">

    {{-- Left panel: conversation list --}}
    <div class="w-72 flex flex-col border-r border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900">
        <div class="p-3 border-b border-zinc-200 dark:border-zinc-800">
            <button
                wire:click="newConversation"
                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors"
            >
                <x-lucide-plus class="w-4 h-4" />
                New Conversation
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $conversation)
                <button
                    wire:click="selectConversation({{ $conversation->id }})"
                    class="w-full text-left px-3 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors border-b border-zinc-100 dark:border-zinc-800/50 {{ $activeConversationId === $conversation->id ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}"
                >
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                        {{ $conversation->title }}
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        {{ $conversation->updated_at->diffForHumans() }}
                    </p>
                </button>
            @empty
                <div class="px-3 py-6 text-center">
                    <p class="text-sm text-zinc-400 dark:text-zinc-600">No conversations yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Right panel: message area --}}
    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-900">
        @if ($activeConversationId === null)
            {{-- Empty state --}}
            <div class="flex-1 flex flex-col items-center justify-center gap-4">
                <x-lucide-message-circle class="w-12 h-12 text-zinc-300 dark:text-zinc-700" />
                <div class="text-center">
                    <p class="text-zinc-500 dark:text-zinc-400 mb-3">Start a conversation with your financial advisor.</p>
                    <button
                        wire:click="newConversation"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors"
                    >
                        New Conversation
                    </button>
                </div>
            </div>
        @else
            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                @forelse ($messages as $message)
                    @if ($message->role === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-[75%] rounded-2xl rounded-tr-sm px-4 py-2.5 bg-blue-600 text-white text-sm">
                                {{ $message->content }}
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start">
                            <div class="max-w-[75%] rounded-2xl rounded-tl-sm px-4 py-2.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-sm">
                                {{ $message->content }}
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="flex items-center justify-center h-full">
                        <p class="text-sm text-zinc-400 dark:text-zinc-600">Send a message to start the conversation.</p>
                    </div>
                @endforelse

                {{-- Streaming indicator --}}
                @if ($isStreaming)
                    <div class="flex justify-start">
                        <div class="max-w-[75%] rounded-2xl rounded-tl-sm px-4 py-2.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-sm">
                            {{ $streamingResponse }}<span class="inline-block w-1.5 h-4 bg-zinc-500 dark:bg-zinc-400 animate-pulse ml-0.5 align-middle"></span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Input area --}}
            <div class="border-t border-zinc-200 dark:border-zinc-800 p-4">
                <form wire:submit="sendMessage" class="flex gap-3">
                    <input
                        wire:model="messageText"
                        type="text"
                        placeholder="Ask your financial advisor..."
                        class="flex-1 px-4 py-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
                        {{ $isStreaming ? 'disabled' : '' }}
                    />
                    <button
                        type="submit"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition-colors"
                        {{ $isStreaming ? 'disabled' : '' }}
                    >
                        @if ($isStreaming)
                            <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                        @else
                            <x-lucide-send class="w-4 h-4" />
                        @endif
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
