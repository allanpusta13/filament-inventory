<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class WizardReviewStep extends Component
{
    protected string $view = 'filament.components.wizard-review-step';

    public function __construct(
        protected string $label = 'REVIEW & VERIFY',
        protected ?Closure $content = null,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function content(Closure $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getContent(Get $get): HtmlString
    {
        if ($this->content) {
            return new HtmlString(($this->content)($get));
        }

        return new HtmlString('');
    }

    public function getViewData(): array
    {
        return [
            'label' => $this->label,
            'content' => $this->content ?? fn () => '',
        ];
    }
}
