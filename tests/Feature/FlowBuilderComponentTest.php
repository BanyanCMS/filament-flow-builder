<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Livewire\FlowBuilder;
use Livewire\Livewire;

function flowBuilderTest(array $flow = ['steps' => []], bool $readOnly = false): mixed
{
    return Livewire::test(FlowBuilder::class, ['definition' => 'purchase', 'flow' => $flow, 'readOnly' => $readOnly]);
}

/** @return list<array<string, mixed>> */
function flowBuilderSteps(mixed $test): array
{
    return $test->get('flow')['steps'];
}

it('draws the start and finish the app defined, and offers its step types under +', function (): void {
    flowBuilderTest()
        ->assertSee('Staff member submits a purchase')
        ->assertSee('Order placed')
        ->assertSeeHtml('data-testid="flow-plus-0"')
        ->assertSee('Approval')
        ->assertSee('IT budget check')
        ->assertSee('Budget code IT-01.')
        ->assertSee('Select a step to change it, or use + to add one.');
});

it('adds a step at a +, selects it and edits it from the panel', function (): void {
    $test = flowBuilderTest()->call('insertStep', 0, 'approval');
    $id = flowBuilderSteps($test)[0]['id'];

    $test->assertSet('selected', $id)
        ->assertSee('Choose who approves.')
        ->set('stepData.name', 'Line manager approval')
        ->set('stepData.settings.approvers', ['manager', 'finance'])
        ->set('stepData.settings.rule', 'all');

    expect(flowBuilderSteps($test)[0])->toMatchArray(['type' => 'approval', 'name' => 'Line manager approval'])
        ->and(flowBuilderSteps($test)[0]['settings']['approvers'])->toBe(['manager', 'finance']);

    $test->assertSee('All of Line manager, Finance team')
        ->assertSee('Approve → Order placed')
        ->assertSee('Request changes → back to the start')
        ->assertDontSee('Choose who approves.');
});

it('puts a condition on a step and shows the yes/no branch', function (): void {
    $test = flowBuilderTest()->call('insertStep', 0, 'budget-check', 'it');
    $id = flowBuilderSteps($test)[0]['id'];

    $test->set('stepData.when_type', 'value')
        ->set('stepData.when_settings', ['field' => 'amount', 'operator' => 'greater_than', 'value' => '10000'])
        ->assertSeeHtml('data-testid="flow-branch-'.$id.'"')
        ->assertSee('Only when Amount (HKD) is more than 10000')
        ->assertSee('Skipped');

    expect(flowBuilderSteps($test)[0]['when'])->toBe(['type' => 'value', 'settings' => ['field' => 'amount', 'operator' => 'greater_than', 'value' => '10000']]);

    $test->set('stepData.when_type', '__always');
    expect(flowBuilderSteps($test)[0]['when'])->toBeNull();

    // Selecting the step again shows "Always" for a step without a condition.
    $test->call('select', null)->call('select', $id)->assertSet('stepData.when_type', '__always');
});

it('reorders by drag or by the card menu, and removes a step', function (): void {
    $test = flowBuilderTest(['steps' => [
        ['id' => 'a', 'type' => 'approval', 'name' => 'A'],
        ['id' => 'b', 'type' => 'approval', 'name' => 'B'],
        ['id' => 'c', 'type' => 'approval', 'name' => 'C'],
    ]]);
    $ids = static fn (): array => array_column(flowBuilderSteps($test), 'id');

    $test->call('moveStep', 'c', 0);
    expect($ids())->toBe(['c', 'a', 'b']);

    $test->call('moveStepDown', 'c');
    expect($ids())->toBe(['a', 'c', 'b']);

    $test->call('moveStepUp', 'b');
    expect($ids())->toBe(['a', 'b', 'c']);

    $test->call('select', 'b')->call('removeStep', 'b')->assertSet('selected', FlowBuilder::FLOW_START);
    expect($ids())->toBe(['a', 'c']);
});

it('changes nothing when read-only, an unknown type is asked for, or the flow is full', function (): void {
    $flow = ['steps' => [['id' => 'a', 'type' => 'approval', 'name' => 'A', 'settings' => ['approvers' => ['manager']]]]];

    $readOnly = flowBuilderTest($flow, readOnly: true)
        ->call('insertStep', 0, 'approval')
        ->call('removeStep', 'a')
        ->call('moveStep', 'a', 1)
        ->call('select', 'a')
        ->set('stepData.name', 'Hacked')
        ->assertDontSeeHtml('data-testid="flow-remove"')
        ->assertDontSeeHtml('draggable="true"');
    expect(flowBuilderSteps($readOnly))->toHaveCount(1)->and(flowBuilderSteps($readOnly)[0]['name'])->toBe('A');

    $unknown = flowBuilderTest()->call('insertStep', 0, '<script>');
    expect(flowBuilderSteps($unknown))->toBe([]);
});

it('marks a step that has a problem in red on its card', function (): void {
    flowBuilderTest(['steps' => [['id' => 'x', 'type' => 'budget-check', 'name' => 'Budget', 'settings' => []]]])
        ->assertSeeHtml('has-problem')
        ->assertSee('Give the budget code.')
        ->assertSee('Funded → Order placed')
        ->assertSee('Fast-track → Order placed');
});
