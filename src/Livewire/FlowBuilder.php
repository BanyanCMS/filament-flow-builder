<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Livewire;

use Banyan\FilamentFlowBuilder\Concerns\InteractsWithFlowBuilder;
use Banyan\FilamentFlowBuilder\Data\Flow;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * The flow builder canvas as a stand-alone component: a column of cards
 * between Start and Finish, a "+" between every pair, drag to reorder, and a
 * settings panel built from the selected step type's Filament schema. The
 * flow is a plain array, bound to the parent with wire:model.
 */
class FlowBuilder extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithFlowBuilder;
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    #[Modelable]
    public array $flow = ['steps' => []];

    #[Locked]
    public string $definition = '';

    #[Locked]
    public bool $readOnly = false;

    /** Show the built-in "Test it as…" dry run button above the canvas. */
    #[Locked]
    public bool $dryRun = true;

    public function mount(): void
    {
        $this->flow = Flow::fromArray($this->flow)->toArray();
    }

    public function flowDefinitionKey(): string
    {
        return $this->definition;
    }

    public function flowIsReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function flowHasDryRun(): bool
    {
        return $this->dryRun;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'flow-builder::livewire.flow-builder';

        return view($view);
    }
}
