<?php

declare(strict_types=1);

namespace App\Livewire\Wizards;

use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class WizardReviewSummary extends Component
{
    public string $view;

    /** @var array|Closure */
    public mixed $state = [];

    public function render(): View
    {
        $state = $this->state instanceof Closure
            ? ($this->state)()
            : $this->state;

        return view($this->view, ['state' => is_array($state) ? $state : []]);
    }
}
