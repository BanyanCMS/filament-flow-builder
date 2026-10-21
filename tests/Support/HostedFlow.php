<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\Concerns\InteractsWithFlowBuilder;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** A host page with the canvas next to its own save, as an app's own editor page would. */
final class HostedFlow extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithFlowBuilder;
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $flow = ['steps' => []];

    public bool $dirty = false;

    public bool $locked = false;

    public ?int $fullAt = null;

    public function flowDefinitionKey(): string
    {
        return 'purchase';
    }

    public function flowIsReadOnly(): bool
    {
        return $this->locked;
    }

    public function flowIsTestable(): bool
    {
        return false;
    }

    public function save(): void
    {
        // The app's own store found a problem the definition does not know about.
        $this->flowProblems = ['start' => ['That title is taken.']];

        if (isset($this->flow['steps'][0]['id'])) {
            $this->flowProblems[$this->flow['steps'][0]['id']] = ['Finance is away this week.'];
        }

        $this->dirty = false;
    }

    public function render(): View
    {
        return view('flow-builder-test::hosted-flow');
    }

    protected function flowChanged(): void
    {
        $this->dirty = true;
    }

    protected function flowFull(int $max): void
    {
        $this->fullAt = $max;
    }
}
