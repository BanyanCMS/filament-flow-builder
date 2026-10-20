<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Data\Condition;
use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Runtime\Events\FlowFinished;
use Banyan\FilamentFlowBuilder\Runtime\Events\FlowReturned;
use Banyan\FilamentFlowBuilder\Runtime\Events\OutcomeChosen;
use Banyan\FilamentFlowBuilder\Runtime\Events\StepReached;
use Banyan\FilamentFlowBuilder\Runtime\Events\StepSkipped;
use Banyan\FilamentFlowBuilder\Runtime\FlowRunner;
use Banyan\FilamentFlowBuilder\Runtime\Position;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseFlow;
use Illuminate\Support\Facades\Event;

function purchaseFlow(): Flow
{
    return new Flow([
        new Step('manager', 'approval', 'Manager', ['approvers' => ['manager']]),
        new Step('budget', 'budget-check', 'Budget', ['budget_code' => 'IT-01']),
        new Step('director', 'approval', 'Director', ['approvers' => ['director']], new Condition('value', ['field' => 'amount', 'operator' => 'greater_than', 'value' => '10000'])),
    ]);
}

it('walks an item through the steps, skipping one whose condition does not hold', function (): void {
    Event::fake();
    $runner = app(FlowRunner::class);
    $definition = new PurchaseFlow;
    $flow = purchaseFlow();

    $at = $runner->start($definition, $flow, ['amount' => 500]);
    expect($at)->toEqual(Position::at('manager'));

    $at = $runner->decide($definition, $flow, $at, 'approve', ['amount' => 500]);
    expect($at)->toEqual(Position::at('budget'));

    // The director only sees purchases over 10,000: this one finishes after the budget check.
    $at = $runner->decide($definition, $flow, $at, 'funded', ['amount' => 500]);
    expect($at->isFinished())->toBeTrue();

    Event::assertDispatched(StepReached::class, 2);
    Event::assertDispatched(StepSkipped::class, fn (StepSkipped $e): bool => $e->step->id === 'director' && $e->definition === 'purchase');
    Event::assertDispatched(OutcomeChosen::class, 2);
    Event::assertDispatched(FlowFinished::class, 1);
});

it('sends an item back to the start, or straight to the finish, as the outcome says', function (): void {
    Event::fake();
    $runner = app(FlowRunner::class);
    $definition = new PurchaseFlow;
    $flow = purchaseFlow();
    $atBudget = Position::at('budget');

    expect($runner->decide($definition, $flow, $atBudget, 'no-budget')->isReturned())->toBeTrue()
        ->and($runner->decide($definition, $flow, $atBudget, 'fast-track')->isFinished())->toBeTrue()
        ->and($runner->decide($definition, $flow, $atBudget, 'funded', ['amount' => 20000]))->toEqual(Position::at('director'))
        ->and($runner->start($definition, new Flow)->isFinished())->toBeTrue();

    Event::assertDispatched(FlowReturned::class, fn (FlowReturned $e): bool => $e->from->id === 'budget');
});

it('refuses an outcome a step does not have, or a position outside the flow', function (): void {
    $runner = app(FlowRunner::class);

    expect(fn () => $runner->decide(new PurchaseFlow, purchaseFlow(), Position::at('manager'), 'funded'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $runner->decide(new PurchaseFlow, purchaseFlow(), Position::at('gone'), 'approve'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $runner->decide(new PurchaseFlow, purchaseFlow(), Position::finished(), 'approve'))->toThrow(InvalidArgumentException::class);
});

it('stores a position as plain values', function (): void {
    expect(Position::fromArray(Position::at('budget')->toArray()))->toEqual(Position::at('budget'))
        ->and(Position::fromArray(Position::finished()->toArray()))->toEqual(Position::finished())
        ->and(Position::fromArray(['state' => 'step']))->toEqual(Position::returned())
        ->and(Position::fromArray('junk'))->toEqual(Position::returned());
});
