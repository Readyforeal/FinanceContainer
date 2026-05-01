<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Categories')]
class extends Component {
};
?>

<div>
    <livewire:categories.category-manager />
</div>
