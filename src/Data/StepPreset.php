<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Data;

/**
 * One entry of the "+" menu: a step type with a ready name and settings
 * ("Approval", "Legal team", "Only for press releases"…). A step type offers
 * one preset by default; it can offer several.
 */
final readonly class StepPreset
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public string $key,
        public string $label,
        public string $description = '',
        public ?string $icon = null,
        public string $name = '',
        public array $settings = [],
        public ?Condition $when = null,
        public ?int $reminderDays = null,
    ) {}
}
