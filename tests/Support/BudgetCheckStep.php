<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Tests\Support;

use Banyan\FlowBuilder\Data\Outcome;
use Banyan\FlowBuilder\Data\Step;
use Banyan\FlowBuilder\Definitions\FlowDefinition;
use Banyan\FlowBuilder\Steps\AbstractStepType;
use Filament\Forms\Components\TextInput;

/** An app's own step type: a budget holder confirms the money is there. */
final class BudgetCheckStep extends AbstractStepType
{
    public static function key(): string
    {
        return 'budget-check';
    }

    public function label(): string
    {
        return 'Budget check';
    }

    public function description(): string
    {
        return 'Confirm the budget code has room for it.';
    }

    public function icon(): string
    {
        return 'heroicon-m-banknotes';
    }

    public function color(): string
    {
        return 'info';
    }

    public function defaults(FlowDefinition $definition): array
    {
        return ['budget_code' => ''];
    }

    public function schema(FlowDefinition $definition): array
    {
        return [TextInput::make('budget_code')->label('Budget code')->live(onBlur: true)];
    }

    public function summary(Step $step, FlowDefinition $definition): array
    {
        $code = $step->settings['budget_code'] ?? '';

        return is_string($code) && $code !== '' ? ['Code '.$code] : [];
    }

    public function outcomes(Step $step, FlowDefinition $definition): array
    {
        return [
            new Outcome('funded', 'Funded', Outcome::NEXT, 'success'),
            new Outcome('no-budget', 'No budget', Outcome::START, 'danger'),
            new Outcome('fast-track', 'Fast-track', Outcome::FINISH, 'primary'),
        ];
    }

    public function validate(Step $step, FlowDefinition $definition): array
    {
        return ($step->settings['budget_code'] ?? '') === '' ? ['Give the budget code.'] : [];
    }
}
