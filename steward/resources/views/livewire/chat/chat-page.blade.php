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

{{-- Fixed full-screen chat layout, offset for nav sidebar (w-60 + ml-3 spacer = 15.75rem) --}}
<div class="fixed inset-0 flex gap-3 p-3" style="left: 15.75rem;">

    {{-- Left panel: conversation history (in container) --}}
    <div class="w-64 flex-shrink-0 flex flex-col rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-3 border-b border-zinc-200 dark:border-zinc-800">
            <button
                wire:click="newConversation"
                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium transition-colors"
            >
                <x-lucide-plus class="w-4 h-4" />
                New Conversation
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $conversation)
                <button
                    wire:click="selectConversation({{ $conversation->id }})"
                    @class([
                        'w-full text-left px-4 py-3 transition-colors border-b border-zinc-100 dark:border-zinc-800/50',
                        'bg-zinc-100 dark:bg-zinc-800' => $activeConversationId === $conversation->id,
                        'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => $activeConversationId !== $conversation->id,
                    ])
                >
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                        {{ $conversation->title }}
                    </p>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">
                        {{ $conversation->updated_at->diffForHumans() }}
                    </p>
                </button>
            @empty
                <div class="px-4 py-8 text-center">
                    <p class="text-sm text-zinc-400 dark:text-zinc-500">No conversations yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Right area: open chat (no container) --}}
    <div class="flex-1 flex flex-col relative">
        @if ($activeConversationId === null)
            {{-- Empty state --}}
            <div class="flex-1 flex flex-col items-center justify-center gap-4">
                <x-lucide-message-circle class="w-12 h-12 text-zinc-300 dark:text-zinc-700" />
                <div class="text-center">
                    <p class="text-zinc-500 dark:text-zinc-400 mb-3">Start a conversation with your financial advisor.</p>
                    <button
                        wire:click="newConversation"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium transition-colors"
                    >
                        New Conversation
                    </button>
                </div>
            </div>
        @else
            {{-- Messages (floating in body, padded at bottom for input dock) --}}
            <div class="flex-1 overflow-y-auto px-8 pt-4 pb-28">
                <div class="max-w-2xl mx-auto space-y-4">
                    @forelse ($messages as $message)
                        @if ($message->role === 'user')
                            <div class="flex justify-end">
                                <div class="max-w-[75%] rounded-2xl rounded-tr-sm px-4 py-2.5 bg-blue-600 text-white text-sm leading-relaxed bubble-user">
                                    {{ $message->content }}
                                </div>
                            </div>
                        @else
                            <div class="flex justify-start">
                                <div class="max-w-[75%] rounded-2xl rounded-tl-sm px-4 py-2.5 bg-white/80 dark:bg-zinc-800/80 text-zinc-900 dark:text-zinc-100 text-sm leading-relaxed bubble-assistant">
                                    {!! nl2br(e($message->content)) !!}
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="flex items-center justify-center h-full min-h-[40vh]">
                            <p class="text-sm text-zinc-400 dark:text-zinc-500">Send a message to start the conversation.</p>
                        </div>
                    @endforelse

                    {{-- Streaming indicator --}}
                    @if ($isStreaming)
                        <div class="flex justify-start">
                            <div class="max-w-[75%] rounded-2xl rounded-tl-sm px-4 py-2.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-sm leading-relaxed">
                                {!! nl2br(e($streamingResponse)) !!}<span class="inline-block w-1.5 h-4 bg-zinc-400 dark:bg-zinc-500 animate-pulse ml-0.5 align-middle"></span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Frosted glass input docked at bottom --}}
            <div class="absolute bottom-0 left-0 right-0 px-8 pb-4 pt-6 backdrop-blur-xl bg-zinc-100/60 dark:bg-zinc-900/60">
                <form wire:submit="sendMessage" class="max-w-2xl mx-auto">
                    <div class="flex gap-3 items-center rounded-2xl border border-white/60 dark:border-zinc-700/60 bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm px-4 py-2.5 shadow-lg shadow-zinc-300/30 dark:shadow-zinc-950/40 bubble-assistant">
                        <input
                            wire:model="messageText"
                            type="text"
                            placeholder="Ask your financial advisor..."
                            class="flex-1 bg-transparent text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 text-sm focus:outline-none disabled:opacity-50 border-none focus:ring-0 ring-0 shadow-none p-0"
                            @disabled($isStreaming)
                        />
                        <button
                            type="submit"
                            class="flex items-center justify-center w-8 h-8 rounded-xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white transition-colors flex-shrink-0"
                            @disabled($isStreaming)
                        >
                            @if ($isStreaming)
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                            @else
                                <x-lucide-arrow-up class="w-4 h-4" />
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
