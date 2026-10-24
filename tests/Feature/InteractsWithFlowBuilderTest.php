<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Concerns\InteractsWithFlowBuilder;
use Banyan\FilamentFlowBuilder\Conditions\ValueCondition;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Livewire\FlowBuilder;
use Banyan\FilamentFlowBuilder\Tests\Support\CountingFlow;
use Banyan\FilamentFlowBuilder\Tests\Support\HostedFlow;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseFlow;
use Livewire\Livewire;

function flowBuilderHostedStep(string $id): array
{
    return ['id' => $id, 'type' => 'approval', 'name' => 'Manager '.$id, 'settings' => ['approvers' => ['manager'], 'rule' => 'any', 'count' => 1], 'when' => null];
}

it('puts the canvas on a host page and tells the host about every change', function (): void {
    $test = Livewire::test(HostedFlow::class)
        ->assertSee('Staff member submits a purchase')
        ->assertDontSee('Test it as')
        ->assertSet('dirty', false)
        ->call('insertFlowStep', 0, 'approval');

    expect($test->get('flow')['steps'])->toHaveCount(1);
    $test->assertSet('dirty', true)->call('save')->assertSet('dirty', false);

    $test->set('flowStepData.name', 'Line manager')->assertSet('dirty', true);
});

it('shows the problems the host found on the right cards', function (): void {
    Livewire::test(HostedFlow::class, ['flow' => ['steps' => [flowBuilderHostedStep('a')]]])
        ->assertDontSee('Finance is away this week.')
        ->call('save')
        ->assertSee('Finance is away this week.')
        ->assertSee('That title is taken.');
});

it('lets the host say what happens when the flow is full', function (): void {
    $steps = array_map(flowBuilderHostedStep(...), array_map(strval(...), range(1, 30)));

    $test = Livewire::test(HostedFlow::class, ['flow' => ['steps' => $steps]])->call('insertFlowStep', 0, 'approval');

    expect($test->get('flow')['steps'])->toHaveCount(30);
    $test->assertSet('fullAt', 30)->assertSet('dirty', false);
});

it('changes nothing when the host says the flow is read-only', function (): void {
    $test = Livewire::test(HostedFlow::class, ['flow' => ['steps' => [flowBuilderHostedStep('a'), flowBuilderHostedStep('b')]], 'locked' => true])
        ->call('insertFlowStep', 0, 'approval')
        ->call('removeFlowStep', 'a')
        ->call('moveFlowStepDown', 'a');

    expect(array_column($test->get('flow')['steps'], 'id'))->toBe(['a', 'b']);
    $test->assertSet('dirty', false);
});

it('selects the Start card after removing the selected step when the flow has settings', function (): void {
    Livewire::test(HostedFlow::class, ['flow' => ['steps' => [flowBuilderHostedStep('a')]]])
        ->call('selectFlowNode', 'a')
        ->call('removeFlowStep', 'a')
        ->assertSet('flowSelected', HostedFlow::FLOW_START);
});

it('uses the definition for new step ids, the "back" label and the Start panel heading', function (): void {
    $definition = new class extends PurchaseFlow
    {
        public function newStepId(): string
        {
            return 'step-fixed';
        }

        public function returnLabel(): string
        {
            return 'back to the requester';
        }

        public function settingsLabel(): string
        {
            return 'Purchase rules';
        }
    };
    app(FlowDefinitionRegistry::class)->register($definition::class);

    $test = Livewire::test(HostedFlow::class)->call('insertFlowStep', 0, 'approval');

    expect($test->get('flow')['steps'][0]['id'])->toBe('step-fixed');
    $test->assertSee('back to the requester')->call('selectFlowNode', HostedFlow::FLOW_START)->assertSee('Purchase rules');
});

it('clears the panel\'s field errors when another card is selected, and keeps the others', function (): void {
    $test = Livewire::test(HostedFlow::class, ['flow' => ['steps' => [flowBuilderHostedStep('a'), flowBuilderHostedStep('b')]]])
        ->call('selectFlowNode', 'a')
        ->call('selectWithErrors', 'b');

    $test->assertHasNoErrors('flowStepData.name')->assertHasErrors('other');
});

it('shows that a read-only flow can only be viewed, on the selected card\'s panel', function (): void {
    Livewire::test(HostedFlow::class, ['flow' => ['steps' => [flowBuilderHostedStep('a')]], 'locked' => true])
        ->call('selectFlowNode', 'a')
        ->assertSee('This flow can only be viewed here.')
        ->assertDontSee('Remove step');
});

it('starts a newly chosen condition from its defaults, so the rule is never empty', function (): void {
    $test = Livewire::test(HostedFlow::class, ['flow' => ['steps' => [flowBuilderHostedStep('a')]]])
        ->call('selectFlowNode', 'a')
        ->set('flowStepData.when_type', ValueCondition::key());

    expect($test->get('flowStepData.when_settings'))->toBe(['field' => 'amount', 'operator' => 'is', 'value' => ''])
        ->and($test->get('flow')['steps'][0]['when'])->toBe(['type' => ValueCondition::key(), 'settings' => ['field' => 'amount', 'operator' => 'is', 'value' => '']]);
});

it('builds the definition once per request, however often the page asks for it', function (): void {
    app(FlowDefinitionRegistry::class)->register(CountingFlow::class);
    CountingFlow::$built = 0;

    $test = Livewire::test(FlowBuilder::class, ['definition' => 'counting', 'flow' => ['steps' => [flowBuilderHostedStep('a'), flowBuilderHostedStep('b')]]]);

    expect(CountingFlow::$built)->toBe(1);

    CountingFlow::$built = 0;
    $test->call('selectFlowNode', 'a')->call('moveFlowStepDown', 'a');

    expect(CountingFlow::$built)->toBe(2);
});

it('adds only public members with a flow name, so a host page can keep its own plain names', function (): void {
    $trait = new ReflectionClass(InteractsWithFlowBuilder::class);
    $names = [
        ...array_map(static fn (ReflectionProperty $p): string => $p->getName(), $trait->getProperties(ReflectionProperty::IS_PUBLIC)),
        ...array_map(static fn (ReflectionMethod $m): string => $m->getName(), $trait->getMethods(ReflectionMethod::IS_PUBLIC)),
    ];

    $plain = array_values(array_filter($names, static fn (string $name): bool => ! str_contains(strtolower($name), 'flow')));

    expect($plain)->toBe([]);
});
