<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Livewire\Component;

new class extends Component {
    public ?int $activeConversationId = null;
    public ?int $confirmingDeleteId = null;
    public bool $showMobileHistory = false;
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
        $this->showMobileHistory = false;
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
        if (!$this->confirmingDeleteId) {
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

        // Show thinking state, clear input, then trigger AI response after render
        $this->isStreaming = true;
        $this->reset('messageText');
        $this->js('$wire.fetchResponse()');
    }

    public function fetchResponse(): void
    {
        // Build conversation history for Ollama
        $history = ChatMessage::where('conversation_id', $this->activeConversationId)->orderBy('created_at')->get()->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])->toArray();

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

        $messages = $this->activeConversationId ? ChatMessage::where('conversation_id', $this->activeConversationId)->orderBy('created_at')->get() : collect();

        return [
            'conversations' => $conversations,
            'messages' => $messages,
        ];
    }
};
?>

{{-- Chat layout -- fully separate mobile vs desktop --}}
{{-- Root: fixed container filling available space --}}
<div class="fixed inset-0 top-14 lg:top-3 lg:right-3 lg:bottom-3 lg:left-[calc(15.75rem+0.75rem)] flex flex-col lg:flex-row lg:gap-3">

    {{-- ==================== DESKTOP SIDEBAR (fixed, not scrollable) ==================== --}}
    <div class="hidden lg:flex w-64 flex-shrink-0 flex-col rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-3 border-b border-zinc-200 dark:border-zinc-800">
            <flux:button wire:click="newConversation" variant="primary" icon="plus" class="w-full">
                New Conversation
            </flux:button>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $conversation)
                <div @class([
                    'group flex items-center border-b border-zinc-100 dark:border-zinc-800/50 transition-colors',
                    'bg-zinc-100 dark:bg-zinc-800' => $activeConversationId === $conversation->id,
                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => $activeConversationId !== $conversation->id,
                ])>
                    <button wire:click="selectConversation({{ $conversation->id }})" class="flex-1 text-left px-4 py-3 min-w-0">
                        <flux:text class="font-medium truncate">{{ $conversation->title }}</flux:text>
                        <flux:text size="sm" class="mt-0.5">{{ $conversation->updated_at->diffForHumans() }}</flux:text>
                    </button>
                    <button wire:click.stop="confirmDelete({{ $conversation->id }})" class="px-3 py-3 text-zinc-300 dark:text-zinc-600 hover:text-red-500 dark:hover:text-red-400 transition-colors flex-shrink-0">
                        <flux:icon.trash-2 variant="mini" class="size-3.5" />
                    </button>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <flux:text size="sm">No conversations yet.</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ==================== CHAT AREA ==================== --}}
    <div class="flex-1 flex flex-col min-h-0 min-w-0 relative">

        {{-- Mobile top bar --}}
        <div class="lg:hidden flex-shrink-0 relative z-10">
            <div class="flex items-center justify-between px-4 py-3">
                <flux:button wire:click="$toggle('showMobileHistory')" variant="ghost" size="sm" icon="menu" />
                <flux:text class="font-medium truncate mx-4">
                    @if ($activeConversationId)
                        {{ $conversations->firstWhere('id', $activeConversationId)?->title ?? 'Chat' }}
                    @else
                        Chat
                    @endif
                </flux:text>
                <flux:button wire:click="newConversation" variant="ghost" size="sm" icon="square-pen" />
            </div>
        </div>

        {{-- Chat content --}}
        @if ($activeConversationId === null)
            <div class="flex-1 flex flex-col items-center justify-center gap-4 pb-24 lg:pb-0">
                <flux:icon.message-circle class="size-12 text-zinc-300 dark:text-zinc-700" />
                <div class="text-center">
                    <flux:text class="mb-3">Start a conversation with your financial advisor.</flux:text>
                    <flux:button wire:click="newConversation" variant="primary">New Conversation</flux:button>
                </div>
            </div>
        @else
            {{-- Messages (scrollable) --}}
            <div class="flex-1 overflow-y-auto px-4 lg:px-8 pt-2 lg:pt-4 pb-36 lg:pb-28">
                <div class="max-w-2xl mx-auto space-y-4">
                    @forelse ($messages as $message)
                        @if ($message->role === 'user')
                            <div class="flex justify-end">
                                <div class="max-w-[85%] lg:max-w-[75%] rounded-2xl rounded-tr-sm px-4 py-2.5 bg-blue-600 text-white text-sm leading-relaxed bubble-user">
                                    {{ $message->content }}
                                </div>
                            </div>
                        @else
                            <div class="flex justify-start {{ $loop->last && !$isStreaming ? 'chat-fade-in' : '' }}">
                                <div class="max-w-[85%] lg:max-w-[75%] rounded-2xl rounded-tl-sm px-4 py-2.5 bg-white/80 dark:bg-zinc-800/80 text-zinc-900 dark:text-zinc-100 text-sm leading-relaxed bubble-assistant">
                                    {!! nl2br(e($message->content)) !!}
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="flex items-center justify-center h-full min-h-[40vh]">
                            <flux:text size="sm">Send a message to start the conversation.</flux:text>
                        </div>
                    @endforelse

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

            {{-- Input (floating above messages) --}}
            <div class="absolute bottom-0 left-0 right-0 px-3 lg:px-8 pb-24 lg:pb-4 pt-8 pointer-events-none bg-gradient-to-t from-white/80 via-white/50 dark:from-zinc-800/80 dark:via-zinc-800/50 to-transparent">
                <form wire:submit="sendMessage" class="max-w-2xl mx-auto pointer-events-auto">
                    <div class="flex gap-3 items-center rounded-3xl border border-white/40 dark:border-white/[0.08] bg-white/60 dark:bg-zinc-800/50 backdrop-blur-xl px-4 py-2.5 shadow-lg shadow-zinc-300/30 dark:shadow-zinc-950/40 bubble-assistant">
                        <input wire:model="messageText" type="text" placeholder="Ask your financial advisor..."
                            class="flex-1 bg-transparent text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 text-sm focus:outline-none disabled:opacity-50 border-none focus:ring-0 ring-0 shadow-none p-0"
                            @disabled($isStreaming) />
                        <flux:button type="submit" variant="primary" size="sm"
                            :icon="$isStreaming ? 'loader-circle' : 'arrow-up'" :disabled="$isStreaming"
                            class="!rounded-xl !size-8 !p-0 flex items-center justify-center" />
                    </div>
                </form>
            </div>
        @endif
    </div>

    {{-- ==================== MOBILE HISTORY DRAWER ==================== --}}
    @if ($showMobileHistory)
        <div class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-black/30 dark:bg-black/50 backdrop-blur-sm" wire:click="$toggle('showMobileHistory')"></div>
            <div class="absolute left-0 top-0 bottom-0 w-72 bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 shadow-xl flex flex-col">
                <div class="p-3 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                    <flux:heading size="sm">Conversations</flux:heading>
                    <flux:button wire:click="$toggle('showMobileHistory')" variant="ghost" size="sm" icon="x" />
                </div>
                <div class="flex-1 overflow-y-auto">
                    @forelse ($conversations as $conversation)
                        <div @class([
                            'group flex items-center border-b border-zinc-100 dark:border-zinc-800/50 transition-colors',
                            'bg-zinc-100 dark:bg-zinc-800' => $activeConversationId === $conversation->id,
                        ])>
                            <button wire:click="selectConversation({{ $conversation->id }})" class="flex-1 text-left px-4 py-3 min-w-0">
                                <flux:text class="font-medium truncate">{{ $conversation->title }}</flux:text>
                                <flux:text size="sm" class="mt-0.5">{{ $conversation->updated_at->diffForHumans() }}</flux:text>
                            </button>
                            <button wire:click.stop="confirmDelete({{ $conversation->id }})" class="px-3 py-3 text-zinc-300 dark:text-zinc-600 hover:text-red-500 dark:hover:text-red-400 transition-colors flex-shrink-0">
                                <flux:icon.trash-2 variant="mini" class="size-3.5" />
                            </button>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <flux:text size="sm">No conversations yet.</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-[100] flex items-center justify-center" wire:keydown.escape="cancelDelete">
            <div class="absolute inset-0 bg-black/30 dark:bg-black/50 backdrop-blur-sm" wire:click="cancelDelete"></div>
            <div class="relative bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex-shrink-0">
                        <flux:icon.trash-2 class="size-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <flux:heading size="sm">Delete conversation</flux:heading>
                        <flux:text size="sm" class="mt-1">This will permanently delete this conversation and all its messages.</flux:text>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <flux:button wire:click="cancelDelete" variant="subtle" type="button">Cancel</flux:button>
                    <flux:button wire:click="deleteConversation" variant="danger" type="button">Delete</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
