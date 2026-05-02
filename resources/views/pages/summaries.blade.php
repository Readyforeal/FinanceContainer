<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Summaries')]
class extends Component {
};
?>

<div>
    <livewire:summaries.summary-archive />
</div>
