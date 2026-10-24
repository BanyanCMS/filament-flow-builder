<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Data;

use Illuminate\Support\Str;

/**
 * One step of a flow: its type (registered in code), its name, the type's
 * settings, and an optional condition.
 */
final readonly class Step
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public string $id,
        public string $type,
        public string $name = '',
        public array $settings = [],
        public ?Condition $when = null,
        public ?int $reminderDays = null,
    ) {}

    public static function newId(): string
    {
        return 'step_'.Str::lower(Str::random(10));
    }

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            name: array_key_exists('name', $changes) && is_string($changes['name']) ? $changes['name'] : $this->name,
            settings: array_key_exists('settings', $changes) ? Arr::stringKeyed($changes['settings']) : $this->settings,
            when: array_key_exists('when', $changes) ? Condition::fromArray($changes['when']) : $this->when,
            reminderDays: array_key_exists('reminder_days', $changes) ? self::days($changes['reminder_days']) : $this->reminderDays,
        );
    }

    /** @return array{id: string, type: string, name: string, settings: array<string, mixed>, when: array{type: string, settings: array<string, mixed>}|null, reminder_days: int|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'settings' => $this->settings,
            'when' => $this->when?->toArray(),
            'reminder_days' => $this->reminderDays,
        ];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_string($data['type'] ?? null) || $data['type'] === '') {
            return null;
        }

        $id = is_string($data['id'] ?? null) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $data['id']) === 1 ? $data['id'] : self::newId();

        return new self(
            id: $id,
            type: $data['type'],
            name: is_string($data['name'] ?? null) ? $data['name'] : '',
            settings: Arr::stringKeyed($data['settings'] ?? []),
            when: Condition::fromArray($data['when'] ?? null),
            reminderDays: self::days($data['reminder_days'] ?? null),
        );
    }

    /** A reminder of 1 to 365 days, or none. */
    public static function days(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 1 ? min(365, (int) $value) : null;
    }
}
