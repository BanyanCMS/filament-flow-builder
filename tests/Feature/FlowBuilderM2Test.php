<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Actions\TestFlowAction;
use Banyan\FilamentFlowBuilder\Data\Condition;
use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Livewire\FlowBuilder;
use Banyan\FilamentFlowBuilder\Steps\ParallelApprovalStep;
use Banyan\FilamentFlowBuilder\Testing\FlowWalker;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseFlow;
use Banyan\FilamentFlowBuilder\Validation\FlowValidator;
use Livewire\Livewire;

function flowBuilderM2(array $flow = ['steps' => []], bool $testable = true): mixed
{
    return Livewire::test(FlowBuilder::class, ['definition' => 'purchase', 'flow' => $flow, 'testable' => $testable]);
}

it('offers every preset of a step type under +, and starts the step from it', function (): void {
    $test = flowBuilderM2()
        ->assertSee('IT budget check')
        ->assertSee('Marketing budget check')
        ->assertSee('Parallel review')
        ->call('insertStep', 0, 'budget-check', 'marketing');

    expect($test->get('flow')['steps'][0])->toMatchArray(['type' => 'budget-check', 'name' => 'Marketing budget', 'settings' => ['budget_code' => 'MK-01']]);

    // A preset the type does not offer adds nothing.
    $test->call('insertStep', 0, 'budget-check', 'nope');
    expect($test->get('flow')['steps'])->toHaveCount(1);
});

it('lets a step type draw its card from the step, and keeps a reminder on remindable steps', function (): void {
    $test = flowBuilderM2()->call('insertStep', 0, 'budget-check', 'it');

    expect($test->get('flow')['steps'][0]['reminder_days'])->toBe(3);

    $test->assertSee('IT budget · Step 1')
        ->assertSee('Reminder after 3 days')
        ->set('stepData.reminder_days', '400');

    // Capped at a year.
    expect($test->get('flow')['steps'][0]['reminder_days'])->toBe(365);

    $test->set('stepData.reminder_days', '');
    expect($test->get('flow')['steps'][0]['reminder_days'])->toBeNull();
});

it('edits the flow\'s own settings on the Start card and shows their problems there', function (): void {
    $test = flowBuilderM2(['steps' => [], 'settings' => ['title' => '']])
        ->assertSee('Give the flow a name.')
        ->call('select', FlowBuilder::FLOW_START)
        ->assertSet('selected', FlowBuilder::FLOW_START)
        ->assertSee('Flow settings')
        ->set('flowSettings.title', 'Office supplies')
        ->set('flowSettings.urgent', true);

    expect($test->get('flow')['settings'])->toMatchArray(['title' => 'Office supplies', 'urgent' => true]);

    $test->assertDontSee('Give the flow a name.')->assertSee('Office supplies');

    // Read-only: the Start panel shows the settings but does not change them.
    $readOnly = Livewire::test(FlowBuilder::class, ['definition' => 'purchase', 'flow' => ['steps' => [], 'settings' => ['title' => 'Kept']], 'readOnly' => true])
        ->call('select', FlowBuilder::FLOW_START)
        ->set('flowSettings.title', 'Changed');
    expect($readOnly->get('flow')['settings']['title'])->toBe('Kept');
});

it('reviews in parallel: each chosen team approves, and at least two are needed', function (): void {
    $definition = new PurchaseFlow;
    $type = new ParallelApprovalStep;
    $step = new Step('p', 'parallel-approval', 'Sign-off', ['teams' => ['legal', 'it']]);

    expect($type->summary($step, $definition))->toBe(['Legal', 'IT', 'each team approves'])
        ->and($type->validate($step, $definition))->toBe([])
        ->and($type->validate($step->with(['settings' => ['teams' => ['legal']]]), $definition))->toBe(['Choose at least two teams.'])
        ->and(array_map(static fn ($o): string => $o->key, $type->outcomes($step, $definition)))->toBe(['approve', 'changes'])
        ->and($type->remindable())->toBeTrue();
});

it('tests the flow on screen: skipped, passed and where it ends', function (): void {
    $flow = new Flow([
        new Step('m', 'approval', 'Manager', ['approvers' => ['manager']]),
        new Step('d', 'approval', 'Director', ['approvers' => ['director']], new Condition('value', ['field' => 'amount', 'operator' => 'greater_than', 'value' => '10000'])),
        new Step('b', 'budget-check', 'Budget', ['budget_code' => 'X']),
    ]);
    $walker = app(FlowWalker::class);

    expect($walker->walk(new PurchaseFlow, $flow, ['amount' => 500])->lines)->toBe([
        'Manager: Approve',
        'Director is skipped (Only when Amount (HKD) is more than 10000).',
        'Budget: Funded',
    ])->and($walker->walk(new PurchaseFlow, $flow, ['amount' => 500])->outcome)->toBe('Order placed')
        ->and($walker->walk(new PurchaseFlow, $flow, ['amount' => 20000])->lines[1])->toBe('Director: Approve')
        ->and((new PurchaseFlow)->test(new Flow, [])->lines)->toBe([]);
});

it('shows a Test it as… button above the canvas that walks the unsaved flow', function (): void {
    $flow = ['steps' => [['id' => 'm', 'type' => 'approval', 'name' => 'Manager', 'settings' => ['approvers' => ['manager']]]], 'settings' => ['title' => 'x']];

    flowBuilderM2($flow)
        ->assertSee('Test it as…')
        ->mountAction('test')
        ->assertActionMounted('test');
    // The modal arrives as a Livewire partial; its content is checked in a browser.

    flowBuilderM2($flow, testable: false)->assertDontSee('Test it as…');

    expect(TestFlowAction::getDefaultName())->toBe('testFlow');
});

it('validates the Start settings with the steps', function (): void {
    $problems = app(FlowValidator::class)->problems(new Flow([], ['title' => '']), new PurchaseFlow);

    expect($problems)->toBe(['start' => ['Give the flow a name.']])
        ->and(Flow::fromArray(['steps' => [], 'settings' => ['title' => 'A']])->toArray())->toBe(['steps' => [], 'settings' => ['title' => 'A']])
        ->and(Step::days('0'))->toBeNull()
        ->and(Step::days('7'))->toBe(7);
});

it('starts a parallel review with the first two teams, so it is valid as soon as it is added', function (): void {
    $test = flowBuilderM2()->call('insertStep', 0, 'parallel-approval');
    $step = $test->get('flow')['steps'][0];
    $teams = array_slice(array_keys((new PurchaseFlow)->teams()), 0, 2);

    expect($step['settings']['teams'])->toBe($teams)
        ->and(app(FlowValidator::class)->problems(Flow::fromArray($test->get('flow')), new PurchaseFlow))->toBe([]);
});
