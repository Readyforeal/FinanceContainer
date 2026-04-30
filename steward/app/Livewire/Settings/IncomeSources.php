<?php

namespace App\Livewire\Settings;

use App\Models\IncomeSource;
use Livewire\Component;

class IncomeSources extends Component
{
    public string $name = '';
    public string $amount = '';
    public string $frequency = 'monthly';
    public string $nextPayDate = '';
    public ?int $editingId = null;
    public ?int $confirmDeleteId = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:weekly,biweekly,monthly',
            'nextPayDate' => 'nullable|date',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'amount' => $this->amount,
            'frequency' => $this->frequency,
            'next_pay_date' => $this->nextPayDate ?: null,
            'is_active' => true,
        ];

        if ($this->editingId) {
            IncomeSource::findOrFail($this->editingId)->update($data);
        } else {
            IncomeSource::create($data);
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $source = IncomeSource::findOrFail($id);
        $this->editingId = $id;
        $this->name = $source->name;
        $this->amount = (string) $source->amount;
        $this->frequency = $source->frequency;
        $this->nextPayDate = $source->next_pay_date?->format('Y-m-d') ?? '';
    }

    public function delete(int $id): void
    {
        IncomeSource::findOrFail($id)->delete();
        $this->confirmDeleteId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->amount = '';
        $this->frequency = 'monthly';
        $this->nextPayDate = '';
        $this->resetValidation();
    }

    public function render()
    {
        $sources = IncomeSource::orderBy('name')->get();
        $totalMonthly = $sources->sum(fn ($s) => $s->monthlyAmount());

        return view('livewire.settings.income-sources', compact('sources', 'totalMonthly'));
    }
}
