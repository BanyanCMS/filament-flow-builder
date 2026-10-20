<?php

declare(strict_types=1);

use Banyan\FlowBuilder\Data\Condition;
use Banyan\FlowBuilder\Data\Flow;
use Banyan\FlowBuilder\Data\Step;
use Banyan\FlowBuilder\Tests\Support\PurchaseFlow;
use Banyan\FlowBuilder\Validation\FlowValidator;

it('says what is wrong with each step, in plain words', function (): void {
    $flow = new Flow([
        new Step('ok', 'approval', 'Manager', ['approvers' => ['manager'], 'rule' => 'any']),
        new Step('noname', 'approval', '  ', ['approvers' => ['manager']]),
        new Step('nobody', 'approval', 'Finance', ['approvers' => ['ghost']]),
        new Step('toomany', 'approval', 'Board', ['approvers' => ['manager'], 'rule' => 'count', 'count' => 3]),
        new Step('gone', 'retired-type', 'Old'),
        new Step('cond', 'approval', 'Director', ['approvers' => ['director']], new Condition('value', ['field' => 'amount', 'operator' => 'greater_than', 'value' => 'lots'])),
        new Step('nocond', 'approval', 'Director', ['approvers' => ['director']], new Condition('retired-condition')),
        new Step('budget', 'budget-check', 'Budget'),
    ]);

    expect(app(FlowValidator::class)->problems($flow, new PurchaseFlow))->toBe([
        'noname' => ['Give the step a name.'],
        'nobody' => ['Choose who approves.'],
        'toomany' => ['Only 1 approver(s) are chosen.'],
        'gone' => ['This kind of step (retired-type) is no longer available.'],
        'cond' => ['Give a number to compare with.'],
        'nocond' => ['This condition (retired-condition) is no longer available.'],
        'budget' => ['Give the budget code.'],
    ])->and(app(FlowValidator::class)->passes(new Flow([$flow->steps[0]]), new PurchaseFlow))->toBeTrue();
});

it('caps the number of steps', function (): void {
    $definition = new class extends PurchaseFlow
    {
        public function maxSteps(): int
        {
            return 1;
        }
    };
    $step = static fn (string $id): Step => new Step($id, 'approval', 'A', ['approvers' => ['manager']]);

    expect(app(FlowValidator::class)->problems(new Flow([$step('a'), $step('b')]), $definition))->toBe(['flow' => ['A flow can have at most 1 steps.']]);
});
