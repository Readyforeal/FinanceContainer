<?php

use App\Enums\BudgetBucket;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $icon = 'tag';
    public string $defaultBucket = 'wants';
    public bool $isEssential = false;
    public ?int $editingId = null;
    public int $lookbackMonths = 3;

    public function mount(): void
    {
        $this->js("setTimeout(() => window.dispatchEvent(new CustomEvent('set-dock-action', { detail: { icon: 'plus' } })), 100)");
    }

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

        $this->modal('category-editor')->close();
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->icon = $category->icon ?? 'tag';
        $this->defaultBucket = $category->default_bucket->value;
        $this->isEssential = (bool) $category->is_essential;
        $this->modal('category-editor')->show();
    }

    #[On('dock-action')]
    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->modal('category-editor')->show();
    }

    public function delete(int $id): void
    {
        Category::findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->modal('category-editor')->close();
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
            'tag', 'house', 'wallet', 'coffee', 'car', 'shopping-bag', 'shopping-cart',
            'utensils', 'zap', 'wifi', 'smartphone', 'tv', 'book', 'heart', 'heart-pulse',
            'music', 'plane', 'train', 'bus', 'dumbbell', 'scissors',
            'gift', 'briefcase', 'building-2', 'piggy-bank', 'banknote',
            'fuel', 'shield', 'wrench', 'hammer', 'stethoscope', 'baby',
            'film', 'repeat', 'puzzle', 'shirt', 'sparkles', 'paint-roller',
            'church', 'circle-alert', 'credit-card', 'flame', 'droplets',
            'arrow-down-to-line', 'arrow-up-from-line',
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
            <flux:heading size="xl">Categories</flux:heading>
            <flux:text class="mt-1">Manage spending categories and track average spend.</flux:text>
        </div>

        <div class="flex items-center gap-3">
            {{-- Lookback selector --}}
            <div class="flex items-center gap-1">
                @foreach ([3, 6, 12] as $months)
                    <flux:button
                        wire:click="setLookback({{ $months }})"
                        :variant="$lookbackMonths === $months ? 'filled' : 'subtle'"
                        size="sm"
                    >
                        {{ $months }}mo
                    </flux:button>
                @endforeach
            </div>

            {{-- Add category button --}}
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Add Category
            </flux:button>
        </div>
    </div>

    {{-- Desktop table --}}
    <div class="hidden lg:block">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Icon</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Bucket</flux:table.column>
                <flux:table.column>Essential</flux:table.column>
                <flux:table.column align="end">Avg/mo</flux:table.column>
                <flux:table.column align="center">Trend</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($categories as $category)
                    @php
                        $bv = $category->default_bucket->value;
                        $bucketColor = match($bv) {
                            'needs' => 'blue', 'wants' => 'violet', 'savings' => 'emerald',
                            'income' => 'sky', 'transfer' => 'zinc', default => 'zinc',
                        };
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:icon :icon="$category->icon ?? 'tag'" variant="mini" class="text-zinc-400 dark:text-zinc-500" />
                        </flux:table.cell>
                        <flux:table.cell class="font-medium">
                            {{ $category->name }}
                            @if ($category->is_system)
                                <flux:badge color="zinc" size="sm" class="ml-2">system</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$bucketColor" size="sm">{{ $bv }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($category->is_essential)
                                <flux:badge color="amber" size="sm">essential</flux:badge>
                            @else
                                <span class="text-zinc-300 dark:text-zinc-600">&mdash;</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">${{ number_format($category->avg_spend, 2) }}</flux:table.cell>
                        <flux:table.cell align="center">
                            @if ($category->trend === 'up')
                                <flux:icon.trending-up class="size-4 text-red-500 mx-auto" />
                            @elseif ($category->trend === 'down')
                                <flux:icon.trending-down class="size-4 text-emerald-500 mx-auto" />
                            @else
                                <flux:icon.minus class="size-4 text-zinc-400 dark:text-zinc-500 mx-auto" />
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button wire:click="edit({{ $category->id }})" variant="subtle" size="xs" icon="pencil" />
                                <flux:button wire:click="delete({{ $category->id }})" wire:confirm="Delete '{{ $category->name }}'?" variant="subtle" size="xs" icon="trash-2" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-8">
                            <flux:text>No categories yet.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Mobile card list --}}
    <div class="lg:hidden space-y-2">
        @forelse ($categories as $category)
            @php
                $bv = $category->default_bucket->value;
                $bucketColor = match($bv) {
                    'needs' => 'blue', 'wants' => 'violet', 'savings' => 'emerald',
                    'income' => 'sky', 'transfer' => 'zinc', default => 'zinc',
                };
            @endphp
            <div class="flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700"
                 wire:click="edit({{ $category->id }})"
                 class="cursor-pointer active:bg-zinc-50 dark:active:bg-zinc-800">
                <flux:icon :icon="$category->icon ?? 'tag'" variant="mini" class="shrink-0 text-zinc-400 dark:text-zinc-500" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <flux:text size="sm" class="font-medium truncate">{{ $category->name }}</flux:text>
                        <span class="shrink-0 text-sm font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($category->avg_spend, 2) }}/mo</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <flux:badge :color="$bucketColor" size="sm">{{ $bv }}</flux:badge>
                        @if ($category->is_essential)
                            <flux:badge color="amber" size="sm">essential</flux:badge>
                        @endif
                        @if ($category->trend === 'up')
                            <flux:icon.trending-up class="size-3.5 text-red-500" />
                        @elseif ($category->trend === 'down')
                            <flux:icon.trending-down class="size-3.5 text-emerald-500" />
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8">
                <flux:text>No categories yet.</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Category Editor Modal --}}
    <flux:modal name="category-editor" class="w-full md:w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Category' : 'New Category' }}</flux:heading>
            </div>

            <flux:input wire:model="name" label="Name" placeholder="Category name" />

            <flux:field>
                <flux:label>Icon</flux:label>
                <div class="flex items-center gap-3">
                    <div class="shrink-0 w-10 h-10 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center">
                        <flux:icon :icon="$icon" class="size-5" />
                    </div>
                    <flux:select wire:model.live="icon" class="flex-1">
                        @foreach ($iconOptions as $iconName)
                            <flux:select.option value="{{ $iconName }}">{{ str_replace('-', ' ', $iconName) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </flux:field>

            <div>
                <flux:label class="mb-2">Budget Bucket</flux:label>
                <div class="flex flex-wrap gap-2">
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
                                'income' => $isSelected
                                    ? 'bg-sky-50 dark:bg-sky-900/30 border-sky-300 dark:border-sky-700 text-sky-700 dark:text-sky-300'
                                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 hover:border-sky-200 dark:hover:border-sky-800',
                                'transfer' => $isSelected
                                    ? 'bg-zinc-100 dark:bg-zinc-800 border-zinc-400 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300'
                                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:click="$set('defaultBucket', '{{ $bucket->value }}')"
                            class="rounded-lg border px-4 py-2 text-sm font-medium text-center transition-colors cursor-pointer {{ $colors }}"
                        >
                            {{ ucfirst($bucket->value) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <flux:switch wire:model="isEssential" label="Essential" description="Mark as a non-negotiable expense" />

            <div class="flex justify-between gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="save" variant="primary">
                    {{ $editingId ? 'Save Changes' : 'Create Category' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
