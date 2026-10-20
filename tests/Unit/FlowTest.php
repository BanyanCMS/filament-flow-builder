<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;

it('reads a stored flow tolerantly: drops broken steps, renames duplicate or unsafe ids', function (): void {
    $flow = Flow::fromArray(['steps' => [
        ['id' => 'a', 'type' => 'approval', 'name' => 'Manager', 'settings' => ['approvers' => ['manager']]],
        ['id' => 'a', 'type' => 'approval', 'name' => 'Duplicate'],
        ['id' => '<script>', 'type' => 'approval'],
        ['type' => ''],
        'nonsense',
    ]]);

    expect($flow->steps)->toHaveCount(3)
        ->and($flow->steps[0]->id)->toBe('a')
        ->and($flow->steps[1]->id)->not->toBe('a')
        ->and($flow->steps[2]->id)->toStartWith('step_')
        ->and(Flow::fromArray($flow->toArray()))->toEqual($flow)
        ->and(Flow::fromArray(null)->steps)->toBe([]);
});

it('inserts, moves and removes steps by id', function (): void {
    $a = new Step('a', 'approval', 'A');
    $b = new Step('b', 'approval', 'B');
    $c = new Step('c', 'approval', 'C');
    $flow = (new Flow([$a, $b]))->insert(1, $c);
    $ids = static fn (Flow $flow): array => array_map(static fn (Step $s): string => $s->id, $flow->steps);

    expect($ids($flow))->toBe(['a', 'c', 'b'])
        ->and($ids($flow->move('a', 3)))->toBe(['c', 'b', 'a'])
        ->and($ids($flow->move('b', 0)))->toBe(['b', 'a', 'c'])
        ->and($ids($flow->move('missing', 0)))->toBe(['a', 'c', 'b'])
        ->and($ids($flow->remove('c')))->toBe(['a', 'b'])
        ->and($flow->find('c')?->name)->toBe('C')
        ->and($flow->find('x'))->toBeNull()
        ->and($flow->replace($c->with(['name' => 'Renamed', 'when' => ['type' => 'value', 'settings' => ['field' => 'amount']]]))->find('c')?->when?->settings)->toBe(['field' => 'amount']);
});
