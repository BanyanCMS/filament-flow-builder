<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Data;

/**
 * "Only when …" on a step: a registered condition type and its settings.
 * When it does not hold, the step is skipped.
 */
final readonly class Condition
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public string $type,
        public array $settings = [],
    ) {}

    /** @return array{type: string, settings: array<string, mixed>} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'settings' => $this->settings];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_string($data['type'] ?? null) || $data['type'] === '') {
            return null;
        }

        return new self($data['type'], Arr::stringKeyed($data['settings'] ?? []));
    }

    /**
     * @deprecated use Arr::stringKeyed(), which is internal to this package
     *
     * @return array<string, mixed>
     */
    public static function stringKeyed(mixed $value): array
    {
        return Arr::stringKeyed($value);
    }
}
