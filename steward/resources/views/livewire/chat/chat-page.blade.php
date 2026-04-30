<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Livewire\Component;

new class extends Component {
    public ?int $activeConversationId = null;
    public ?int $confirmingDeleteId = null;
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

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteConversation(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $conversation = ChatConversation::where('id', $this->confirmingDeleteId)
            ->where('user_id', auth()->id())
            ->first();

        if ($conversation) {
            $conversation->messages()->delete();
            $conversation->delete();

            if ($this->activeConversationId === $this->confirmingDeleteId) {
                $next = ChatConversation::where('user_id', auth()->id())
                    ->latest()
                    ->first();
                $this->activeConversationId = $next?->id;
            }
        }

        $this->confirmingDeleteId = null;
    }

    public function sendMessage(): void
    {
        if (blank($this->messageText) || $this->isStreaming || $this->activeConversationId === null) {
            return;
        }

        $text = $this->messageText;
        $this->messageText = '';

        // Create the user message immediately so it renders
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

        // Show thinking state, then trigger AI response after this render completes
        $this->isStreaming = true;
        $this->js('$wire.fetchResponse()');
    }

    public function fetchResponse(): void
    {
        // Build conversation history for Ollama
        $history = ChatMessage::where('conversation_id', $this->activeConversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();

        // Build prompts using financial context
        $contextBuilder = app(FinancialContextBuilder::class);
        $systemPrompt = $contextBuilder->buildSystemPrompt() . "\n\n" . $contextBuilder->buildDynamicContext();

        $fullResponse = '';

        try {
            $ollama = app(OllamaService::class);
            $ollama->streamChat($systemPrompt, $history, function (string $token) use (&$fullResponse): void {
                $fullResponse .= $token;
            });
        } catch (\Throwable $e) {
            $fullResponse = "I'm unable to connect to the AI service right now. Please try again later.\n\nError: " . $e->getMessage();
        }

        // Persist the assistant message
        ChatMessage::create([
            'conversation_id' => $this->activeConversationId,
            'user_id' => auth()->id(),
            'role' => 'assistant',
            'content' => $fullResponse,
        ]);

        $this->isStreaming = false;
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
                <div
                    @class([
                        'group flex items-center border-b border-zinc-100 dark:border-zinc-800/50 transition-colors',
                        'bg-zinc-100 dark:bg-zinc-800' => $activeConversationId === $conversation->id,
                        'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => $activeConversationId !== $conversation->id,
                    ])
                >
                    <button
                        wire:click="selectConversation({{ $conversation->id }})"
                        class="flex-1 text-left px-4 py-3 min-w-0"
                    >
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                            {{ $conversation->title }}
                        </p>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">
                            {{ $conversation->updated_at->diffForHumans() }}
                        </p>
                    </button>
                    <button
                        wire:click.stop="confirmDelete({{ $conversation->id }})"
                        class="px-3 py-3 text-zinc-300 dark:text-zinc-600 hover:text-red-500 dark:hover:text-red-400 transition-colors flex-shrink-0"
                    >
                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    </button>
                </div>
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
                            <div class="flex justify-start {{ $loop->last && !$isStreaming ? 'chat-fade-in' : '' }}">
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

                    {{-- Thinking indicator --}}
                    @if ($isStreaming)
                        <div class="flex justify-start">
                            <div class="rounded-2xl rounded-tl-sm px-4 py-3 bg-white/80 dark:bg-zinc-800/80 bubble-assistant">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-[thinking_1.4s_ease-in-out_infinite]"></span>
                                    <span class="w-2 h-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-[thinking_1.4s_ease-in-out_0.2s_infinite]"></span>
                                    <span class="w-2 h-2 rounded-full bg-zinc-400 dark:bg-zinc-500 animate-[thinking_1.4s_ease-in-out_0.4s_infinite]"></span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Input docked at bottom --}}
            <div class="absolute bottom-0 left-0 right-0 px-8 pb-4 pt-6 pointer-events-none">
                <form wire:submit="sendMessage" class="max-w-2xl mx-auto pointer-events-auto">
                    <div class="flex gap-3 items-center rounded-2xl border border-white/40 dark:border-white/[0.08] bg-white/60 dark:bg-zinc-800/50 backdrop-blur-xl px-4 py-2.5 shadow-lg shadow-zinc-300/30 dark:shadow-zinc-950/40 bubble-assistant">
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

    {{-- Delete confirmation modal --}}
    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-[100] flex items-center justify-center" wire:keydown.escape="cancelDelete">
            <div class="absolute inset-0 bg-black/30 dark:bg-black/50 backdrop-blur-sm" wire:click="cancelDelete"></div>

            <div class="relative bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex-shrink-0">
                        <x-lucide-trash-2 class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Delete conversation</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">This will permanently delete this conversation and all its messages.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        wire:click="cancelDelete"
                        class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors"
                        type="button"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="deleteConversation"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-500 rounded-lg transition-colors"
                        type="button"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
