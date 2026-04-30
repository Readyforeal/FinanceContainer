<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Budgets')]
class extends Component {
};
?>

<div>
    <livewire:budgets.budget-manager />
</div>
