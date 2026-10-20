# Filament Flow Builder

A flexible, card-based **workflow builder for Filament v5**. You define, in code, the kinds of steps and conditions a flow may use. People then design their own flows with a visual editor:

- Start and Finish cards;
- a "+" between every pair of steps;
- drag to reorder;
- "only when …" conditions, drawn as a Yes/No branch;
- a settings panel per step.

A small runner moves items through a saved flow and fires events. Notifications, tasks and side effects stay in **your** app.

Extracted from [Banyan CMS](https://github.com/BanyanCMS/banyan)'s workflow editor. Licensed **MIT**.

## Requirements

PHP 8.3+, Laravel 13, Filament 5, Livewire 4.

## Install

```bash
composer require banyancms/filament-flow-builder
php artisan filament:assets
```

## 1. Define a kind of flow

```php
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;

class PurchaseFlow extends FlowDefinition
{
    public static function key(): string { return 'purchase'; }

    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class, BudgetCheckStep::class];   // what "+" offers
    }

    public function startLabel(): string { return 'Staff member submits a purchase'; }
    public function finishLabel(): string { return 'Order placed'; }

    // Who the built-in approval step can ask.
    public function approvers(): array
    {
        return ['manager' => 'Line manager', 'finance' => 'Finance team', 'director' => 'Director'];
    }

    // What the built-in "a value matches" condition can check.
    public function contextFields(): array
    {
        return ['amount' => 'Amount (HKD)', 'department' => 'Department'];
    }
}
```

Register it on your panel:

```php
->plugin(FlowBuilderPlugin::make()->definitions([PurchaseFlow::class]))
```

## 2. Put the builder in a form

```php
use Banyan\FilamentFlowBuilder\Forms\FlowBuilderField;

FlowBuilderField::make('approval_flow')->definition(PurchaseFlow::class)
```

The field's state is plain data (`{"steps": [...]}`); store it in a JSON column. A flow with problems (a step without approvers, a condition without a value, …) does not save. Each problem is shown in red on its card.

## 3. Add your own steps

Extend `AbstractStepType` (or implement `StepType`):

```php
class BudgetCheckStep extends AbstractStepType
{
    public static function key(): string { return 'budget-check'; }
    public function label(): string { return 'Budget check'; }
    public function description(): string { return 'Confirm the budget code has room for it.'; }
    public function icon(): string { return 'heroicon-m-banknotes'; }
    public function color(): string { return 'info'; }

    // The settings panel: any Filament fields.
    public function schema(FlowDefinition $definition): array
    {
        return [TextInput::make('budget_code')->label('Budget code')->live(onBlur: true)];
    }

    // Chips on the card.
    public function summary(Step $step, FlowDefinition $definition): array
    {
        return filled($step->settings['budget_code'] ?? null) ? ['Code '.$step->settings['budget_code']] : [];
    }

    // The ways out, and where each one goes.
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
        return blank($step->settings['budget_code'] ?? null) ? ['Give the budget code.'] : [];
    }
}
```

## 4. Add your own conditions

Implement `ConditionType`:
- `key()` and `label()`;
- `schema()`: its settings fields;
- `describe()`: the sentence on the card;
- `passes($settings, $context)`;
- `validate()`.

List the class in `conditionTypeClasses()` on your definition. The built-in `ValueCondition` compares one of your `contextFields()` with a value (is, is not, more than, less than, contains).

## 5. Run a flow

```php
$runner = app(FlowRunner::class);
$flow = Flow::fromArray($request->approval_flow);
$context = ['amount' => $purchase->amount, 'department' => $purchase->department];

$position = $runner->start(new PurchaseFlow, $flow, $context);       // at the first step that applies
$position = $runner->decide(new PurchaseFlow, $flow, $position, 'approve', $context);

$purchase->update(['flow_position' => $position->toArray()]);       // store it; it is plain data
```

The runner is stateless and fires events. Listen to them for notifications, audit and side effects:

| Event | When |
|---|---|
| `StepReached` | An item arrives at a step: notify its approvers, create tasks |
| `StepSkipped` | A step's condition did not hold |
| `OutcomeChosen` | Someone approved, rejected, … |
| `FlowReturned` | Back to whoever started it |
| `FlowFinished` | Every step is done |

## Try it

```bash
composer install
composer serve        # http://127.0.0.1:8090/admin/purchase-flow
composer test         # Pest
composer analyse      # PHPStan level 9
```

The workbench panel (`workbench/`) uses the `PurchaseFlow` and `BudgetCheckStep` from `tests/Support`.

## Look and feel

Every colour comes from the panel's own palette (`--primary-*`, `--gray-*`, `--success-*`, …), so the builder follows your theme and dark mode. The interface is in English, Traditional Chinese and Simplified Chinese. Publish the translations with `php artisan vendor:publish --tag=flow-builder-translations` to change the wording.

## Roadmap

1. **M1: the plugin** ✅ Data, field, canvas, drag, conditions, validator, runner, events, three languages, tests.
2. **M2: Banyan uses it.** Banyan's *Settings → Workflows* renders through this plugin, with no change in behaviour.
3. **M3: demo and docs.** Screenshots, more built-in step types if useful (parallel approval, notify-only), and a Packagist release when ready.
