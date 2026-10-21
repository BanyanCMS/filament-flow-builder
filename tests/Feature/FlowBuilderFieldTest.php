<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Forms\FlowBuilderField;
use Banyan\FilamentFlowBuilder\Livewire\FlowBuilder;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseForm;
use Livewire\Livewire;

it('puts the builder in a form and saves a valid flow as plain data', function (): void {
    $flow = ['steps' => [['id' => 'm', 'type' => 'approval', 'name' => 'Manager', 'settings' => ['approvers' => ['manager'], 'rule' => 'any', 'count' => 1], 'when' => null]]];

    $form = Livewire::test(PurchaseForm::class)
        ->assertSeeLivewire(FlowBuilder::class)
        ->assertSee('Purchase approval')
        ->set('data.flow', $flow)
        ->call('save')
        ->assertHasNoErrors();

    expect($form->get('saved'))->toBe(['flow' => Flow::fromArray($flow)->toArray()])
        ->and($form->get('saved')['flow']['steps'][0]['settings']['approvers'])->toBe(['manager']);
});

it('refuses to save a flow with problems', function (): void {
    Livewire::test(PurchaseForm::class)
        ->set('data.flow', ['steps' => [['id' => 'm', 'type' => 'approval', 'name' => '', 'settings' => []]]])
        ->call('save')
        ->assertHasErrors(['data.flow']);
});

it('takes a definition by class or by its registered key, and nothing else', function (): void {
    expect(FlowBuilderField::make('a')->definition('purchase')->getDefinitionKey())->toBe('purchase')
        ->and(fn () => FlowBuilderField::make('b')->definition('not-registered'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(FlowDefinitionRegistry::class)->register(stdClass::class))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(FlowDefinitionRegistry::class)->get('nope'))->toThrow(InvalidArgumentException::class);
});
