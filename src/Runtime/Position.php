<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Runtime;

/**
 * Where an item stands in a flow. The host stores it with the item (it is
 * three plain values) and hands it back to the runner with each decision.
 */
final readonly class Position
{
    public const string AT_STEP = 'step';

    public const string FINISHED = 'finished';

    public const string RETURNED = 'returned';

    /** @param self::AT_STEP|self::FINISHED|self::RETURNED $state */
    public function __construct(
        public string $state,
        public ?string $stepId = null,
    ) {}

    public static function at(string $stepId): self
    {
        return new self(self::AT_STEP, $stepId);
    }

    public static function finished(): self
    {
        return new self(self::FINISHED);
    }

    public static function returned(): self
    {
        return new self(self::RETURNED);
    }

    public function isFinished(): bool
    {
        return $this->state === self::FINISHED;
    }

    public function isReturned(): bool
    {
        return $this->state === self::RETURNED;
    }

    /** @return array{state: string, step_id: string|null} */
    public function toArray(): array
    {
        return ['state' => $this->state, 'step_id' => $this->stepId];
    }

    public static function fromArray(mixed $data): self
    {
        $state = is_array($data) ? ($data['state'] ?? null) : null;
        $step = is_array($data) && is_string($data['step_id'] ?? null) ? $data['step_id'] : null;

        return match ($state) {
            self::AT_STEP => $step === null ? self::returned() : self::at($step),
            self::FINISHED => self::finished(),
            default => self::returned(),
        };
    }
}
