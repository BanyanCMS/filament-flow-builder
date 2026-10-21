<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Tests\Support\HostedFlow;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseFlow;
use Livewire\Livewire;

function hostedStep(string $id): array
{
    return ['id' => $id, 'type' => 'approval', 'name' => 'Manager '.$id, 'settings' => ['approvers' => ['manager'], 'rule' => 'any', 'count' => 1], 'when' => null];
}

it('puts the canvas on a host page and tells the host about every change', function (): void {
    $test = Livewire::test(HostedFlow::class)
        ->assertSee('Staff member submits a purchase')
        ->assertDontSee('Test it as')
        ->assertSet('dirty', false)
        ->call('insertStep', 0, 'approval');

    expect($test->get('flow')['steps'])->toHaveCount(1);
    $test->assertSet('dirty', true)->call('save')->assertSet('dirty', false);

    $test->set('stepData.name', 'Line manager')->assertSet('dirty', true);
});

it('shows the problems the host found on the right cards', function (): void {
    Livewire::test(HostedFlow::class, ['flow' => ['steps' => [hostedStep('a')]]])
        ->assertDontSee('Finance is away this week.')
        ->call('save')
        ->assertSee('Finance is away this week.')
        ->assertSee('That title is taken.');
});

it('lets the host say what happens when the flow is full', function (): void {
    $steps = array_map(hostedStep(...), array_map(strval(...), range(1, 30)));

    $test = Livewire::test(HostedFlow::class, ['flow' => ['steps' => $steps]])->call('insertStep', 0, 'approval');

    expect($test->get('flow')['steps'])->toHaveCount(30);
    $test->assertSet('fullAt', 30)->assertSet('dirty', false);
});

it('changes nothing when the host says the flow is read-only', function (): void {
    $test = Livewire::test(HostedFlow::class, ['flow' => ['steps' => [hostedStep('a'), hostedStep('b')]], 'locked' => true])
        ->call('insertStep', 0, 'approval')
        ->call('removeStep', 'a')
        ->call('moveStepDown', 'a');

    expect(array_column($test->get('flow')['steps'], 'id'))->toBe(['a', 'b']);
    $test->assertSet('dirty', false);
});

it('selects the Start card after removing the selected step when the flow has settings', function (): void {
    Livewire::test(HostedFlow::class, ['flow' => ['steps' => [hostedStep('a')]]])
        ->call('select', 'a')
        ->call('removeStep', 'a')
        ->assertSet('selected', HostedFlow::FLOW_START);
});

it('uses the definition for new step ids and the "back" label', function (): void {
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
    };
    app(FlowDefinitionRegistry::class)->register($definition::class);

    $test = Livewire::test(HostedFlow::class)->call('insertStep', 0, 'approval');

    expect($test->get('flow')['steps'][0]['id'])->toBe('step-fixed');
    $test->assertSee('back to the requester');
});
