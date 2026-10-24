<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Actions;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\DryRun\DryRunResult;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;

/**
 * "Test it as…": a modal that asks for the definition's test input and shows,
 * as the input changes, what would happen to such an item in the flow as it
 * is on screen. Nothing is saved. Put it anywhere (the builder shows one
 * above its canvas) with ->definition() and ->flowUsing().
 */
class DryRunAction extends Action
{
    protected string $definitionKey = '';

    protected ?Closure $flowUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'flowDryRun';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('flow-builder::flow.test.button'))
            ->icon('heroicon-m-play')
            ->color('gray')
            ->modalHeading(__('flow-builder::flow.test.heading'))
            ->modalDescription(__('flow-builder::flow.test.help'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('flow-builder::flow.test.close'))
            ->schema(fn (): array => [
                ...$this->resolveDefinition()->dryRunSchema(),
                TextEntry::make('route')
                    ->label(__('flow-builder::flow.test.route'))
                    ->state(fn (Get $get): array => $this->result($get)->lines)
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder(__('flow-builder::flow.test.no_steps')),
                TextEntry::make('outcome')
                    ->label(__('flow-builder::flow.test.outcome'))
                    ->state(fn (Get $get): string => $this->result($get)->outcome)
                    ->badge(),
            ]);
    }

    public function definition(string $key): static
    {
        $this->definitionKey = $key;

        return $this;
    }

    /** @param Closure(): Flow $flow the flow as it is now, saved or not */
    public function flowUsing(Closure $flow): static
    {
        $this->flowUsing = $flow;

        return $this;
    }

    private function resolveDefinition(): FlowDefinition
    {
        return app(FlowDefinitionRegistry::class)->get($this->definitionKey);
    }

    private function result(Get $get): DryRunResult
    {
        $definition = $this->resolveDefinition();
        $flow = $this->flowUsing === null ? new Flow : $this->evaluate($this->flowUsing);
        $input = [];

        foreach ($definition->dryRunSchema() as $component) {
            if ($component instanceof Field) {
                $name = (string) $component->getName();
                $input[$name] = $get($name);
            }
        }

        return $definition->dryRun($flow instanceof Flow ? $flow : new Flow, $input);
    }
}
