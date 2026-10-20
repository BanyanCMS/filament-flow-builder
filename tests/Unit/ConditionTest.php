<?php

declare(strict_types=1);

use Banyan\FlowBuilder\Conditions\ValueCondition;
use Banyan\FlowBuilder\Tests\Support\PurchaseFlow;

it('compares a value the app provides with what the designer typed', function (string $operator, mixed $actual, string $value, bool $passes): void {
    expect((new ValueCondition)->passes(['field' => 'amount', 'operator' => $operator, 'value' => $value], ['amount' => $actual], new PurchaseFlow))->toBe($passes);
})->with([
    ['is', 'IT', 'IT', true],
    ['is', 'IT', 'HR', false],
    ['is_not', 'IT', 'HR', true],
    ['greater_than', 15000, '10000', true],
    ['greater_than', 500, '10000', false],
    ['greater_than', 'n/a', '10000', false],
    ['less_than', 500, '10000', true],
    ['contains', 'Information Technology', 'tech', true],
    ['contains', ['IT', 'HR'], 'HR', true],
    ['contains', ['IT'], 'HR', false],
    ['is', true, '1', true],
    ['unknown', 'x', 'x', false],
]);

it('never passes when the app does not provide the value, and reads as a sentence', function (): void {
    $condition = new ValueCondition;
    $settings = ['field' => 'amount', 'operator' => 'greater_than', 'value' => '10000'];

    expect($condition->passes($settings, [], new PurchaseFlow))->toBeFalse()
        ->and($condition->passes(['operator' => 'is'], ['amount' => 1], new PurchaseFlow))->toBeFalse()
        ->and($condition->describe($settings, new PurchaseFlow))->toBe('Only when Amount (HKD) is more than 10000')
        ->and($condition->describe([], new PurchaseFlow))->toBe('Only when … is …')
        ->and($condition->validate(['field' => 'nope'], new PurchaseFlow))->toBe(['Choose which value to check.']);
});
