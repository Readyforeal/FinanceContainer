<?php

use App\Enums\BudgetBucket;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $icon = 'tag';
    public string $defaultBucket = 'wants';
    public bool $isEssential = false;
    public ?int $editingId = null;
    public bool $showModal = false;
    public int $lookbackMonths = 3;

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        if ($this->editingId) {
            Category::findOrFail($this->editingId)->update([
                'name' => $this->name,
                'icon' => $this->icon,
                'default_bucket' => $this->defaultBucket,
                'is_essential' => $this->isEssential,
            ]);
        } else {
            Category::create([
                'name' => $this->name,
                'icon' => $this->icon,
                'default_bucket' => $this->defaultBucket,
                'is_essential' => $this->isEssential,
                'is_system' => false,
            ]);
        }

        $this->closeModal();
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->icon = $category->icon ?? 'tag';
        $this->defaultBucket = $category->default_bucket->value;
        $this->isEssential = (bool) $category->is_essential;
        $this->showModal = true;
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function delete(int $id): void
    {
        Category::findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->showModal = false;
    }

    public function cancelEdit(): void
    {
        $this->closeModal();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->icon = 'tag';
        $this->defaultBucket = 'wants';
        $this->isEssential = false;
        $this->editingId = null;
    }

    public function setLookback(int $months): void
    {
        $this->lookbackMonths = $months;
    }

    public function with(): array
    {
        $categories = Category::orderBy('name')->get()->map(function ($cat) {
            $cat->avg_spend = $cat->averageSpend($this->lookbackMonths);

            // Trend: compare last month vs the average (spending only — negative amounts)
            $lastMonthSpend = (float) abs(Transaction::where('category_id', $cat->id)
                ->whereYear('date', now()->subMonth()->year)
                ->whereMonth('date', now()->subMonth()->month)
                ->where('amount', '<', 0)
                ->sum('amount'));

            $avg = $cat->avg_spend;

            if ($avg > 0 && $lastMonthSpend > $avg * 1.05) {
                $cat->trend = 'up';
            } elseif ($avg > 0 && $lastMonthSpend < $avg * 0.95) {
                $cat->trend = 'down';
            } else {
                $cat->trend = 'flat';
            }

            return $cat;
        });

        $iconOptions = [
            'tag', 'home', 'wallet', 'coffee', 'car', 'shopping-bag', 'shopping-cart',
            'utensils', 'zap', 'wifi', 'smartphone', 'tv', 'book', 'heart', 'heart-pulse',
            'music', 'plane', 'train', 'bus', 'dumbbell', 'scissors',
            'gift', 'briefcase', 'building-2', 'piggy-bank', 'banknote',
            'fuel', 'shield', 'wrench', 'hammer', 'stethoscope', 'baby',
            'film', 'repeat', 'puzzle', 'shirt', 'sparkles', 'paint-roller',
            'church', 'help-circle', 'flame', 'droplets',
        ];

        sort($iconOptions);

        return compact('categories', 'iconOptions');
    }
};
?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Categories</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Manage spending categories and track average spend.</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Lookback selector --}}
            <div class="flex items-center gap-1 rounded-lg border border-zinc-200 dark:border-zinc-700 p-1 bg-white dark:bg-zinc-900">
                @foreach ([3, 6, 12] as $months)
                    <button
                        wire:click="setLookback({{ $months }})"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors
                            {{ $lookbackMonths === $months
                                ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100'
                                : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
                    >
                        {{ $months }}mo
                    </button>
                @endforeach
            </div>

            {{-- Add category button --}}
            <button
                wire:click="openCreateModal"
                class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 px-3.5 py-2 text-sm font-medium hover:bg-zinc-700 dark:hover:bg-zinc-300 transition-colors"
            >
                <x-lucide-plus class="w-4 h-4" />
                Add Category
            </button>
        </div>
    </div>

    {{-- Categories table --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-100 dark:border-zinc-800">
                    <th class="text-left px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400 w-10">Icon</th>
                    <th class="text-left px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">Name</th>
                    <th class="text-left px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">Bucket</th>
                    <th class="text-left px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">Essential</th>
                    <th class="text-right px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">Avg/mo</th>
                    <th class="text-center px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">Trend</th>
                    <th class="text-right px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                        {{-- Icon --}}
                        <td class="px-4 py-4">
                            <x-dynamic-component
                                :component="'lucide-' . ($category->icon ?? 'tag')"
                                class="w-4 h-4 text-zinc-400 dark:text-zinc-500"
                            />
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-4 font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $category->name }}
                            @if ($category->is_system)
                                <span class="ml-2 inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                    system
                                </span>
                            @endif
                        </td>

                        {{-- Bucket badge --}}
                        <td class="px-4 py-4">
                            @php
                                $bucketColors = [
                                    'needs' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400',
                                    'wants' => 'bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400',
                                    'savings' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400',
                                ];
                                $bv = $category->default_bucket->value;
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $bucketColors[$bv] ?? '' }}">
                                {{ $bv }}
                            </span>
                        </td>

                        {{-- Essential badge --}}
                        <td class="px-4 py-4">
                            @if ($category->is_essential)
                                <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-900/20 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                                    essential
                                </span>
                            @else
                                <span class="text-zinc-300 dark:text-zinc-600">&mdash;</span>
                            @endif
                        </td>

                        {{-- Avg spend --}}
                        <td class="px-4 py-4 text-right text-zinc-700 dark:text-zinc-300">
                            ${{ number_format($category->avg_spend, 2) }}
                        </td>

                        {{-- Trend --}}
                        <td class="px-4 py-4 text-center">
                            @if ($category->trend === 'up')
                                <x-lucide-trending-up class="w-4 h-4 text-red-500 mx-auto" />
                            @elseif ($category->trend === 'down')
                                <x-lucide-trending-down class="w-4 h-4 text-emerald-500 mx-auto" />
                            @else
                                <x-lucide-minus class="w-4 h-4 text-zinc-400 dark:text-zinc-500 mx-auto" />
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    wire:click="edit({{ $category->id }})"
                                    class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                    title="Edit"
                                >
                                    <x-lucide-pencil class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    wire:click="delete({{ $category->id }})"
                                    wire:confirm="Delete '{{ $category->name }}'? Transactions using this category will become uncategorized."
                                    class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                    title="Delete"
                                >
                                    <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-zinc-400 dark:text-zinc-500">
                            No categories yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div
            x-data
            x-on:keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-[999] flex items-center justify-center p-4"
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                wire:click="closeModal"
            ></div>

            {{-- Modal content --}}
            <div class="relative z-10 w-full rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xl" style="max-width: 28rem;">
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $editingId ? 'Edit Category' : 'New Category' }}
                    </h3>
                    <button
                        wire:click="closeModal"
                        class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                    >
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-5">
                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Name</label>
                        <input
                            wire:model="name"
                            type="text"
                            placeholder="Category name"
                            class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 placeholder-zinc-400 dark:placeholder-zinc-500"
                            autofocus
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Icon --}}
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Icon</label>
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 w-10 h-10 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center">
                                <x-dynamic-component :component="'lucide-' . $icon" class="w-5 h-5 text-zinc-600 dark:text-zinc-300" />
                            </div>
                            <select
                                wire:model.live="icon"
                                class="flex-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                            >
                                @foreach ($iconOptions as $iconName)
                                    <option value="{{ $iconName }}">{{ str_replace('-', ' ', $iconName) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Bucket --}}
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Budget Bucket</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (App\Enums\BudgetBucket::cases() as $bucket)
                                @php
                                    $isSelected = $defaultBucket === $bucket->value;
                                    $colors = match($bucket->value) {
                                        'needs' => $isSelected
                                            ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300'
                                            : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 hover:border-blue-200 dark:hover:border-blue-800',
                                        'wants' => $isSelected
                                            ? 'bg-violet-50 dark:bg-violet-900/30 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300'
                                            : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 hover:border-violet-200 dark:hover:border-violet-800',
                                        'savings' => $isSelected
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300'
                                            : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 hover:border-emerald-200 dark:hover:border-emerald-800',
                                    };
                                @endphp
                                <button
                                    type="button"
                                    wire:click="$set('defaultBucket', '{{ $bucket->value }}')"
                                    class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors cursor-pointer {{ $colors }}"
                                >
                                    {{ ucfirst($bucket->value) }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Essential toggle --}}
                    <label
                        class="flex items-center gap-3 cursor-pointer"
                        x-data="{ on: @entangle('isEssential') }"
                        x-on:click="on = !on"
                    >
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="on"
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors duration-200"
                            :class="on ? 'bg-amber-500' : 'bg-zinc-300 dark:bg-zinc-600'"
                        >
                            <span
                                class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow-sm transform transition-transform duration-200 mt-0.5"
                                :class="on ? 'translate-x-4 ml-0.5' : 'translate-x-0 ml-0.5'"
                            ></span>
                        </button>
                        <div>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Essential</span>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">Mark as a non-negotiable expense</p>
                        </div>
                    </label>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button
                        wire:click="closeModal"
                        class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="save"
                        class="rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 px-4 py-2 text-sm font-medium hover:bg-zinc-700 dark:hover:bg-zinc-300 transition-colors"
                    >
                        {{ $editingId ? 'Save Changes' : 'Create Category' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
