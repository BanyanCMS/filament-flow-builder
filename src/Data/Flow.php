<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Data;

/**
 * A flow as people design it: an ordered list of steps between a fixed start
 * and finish. A plain value — the host app stores it (usually as JSON) and
 * decides what it means; the builder only edits it.
 */
final readonly class Flow
{
    /** @param list<Step> $steps */
    public function __construct(public array $steps = []) {}

    public static function fromArray(mixed $data): self
    {
        $steps = [];
        $seen = [];

        foreach (is_array($data) && is_array($data['steps'] ?? null) ? $data['steps'] : [] as $raw) {
            $step = Step::fromArray($raw);

            if ($step === null) {
                continue;
            }

            // Ids are unique within a flow; a duplicate gets a fresh one.
            if (isset($seen[$step->id])) {
                $step = new Step(Step::newId(), $step->type, $step->name, $step->settings, $step->when);
            }

            $seen[$step->id] = true;
            $steps[] = $step;
        }

        return new self($steps);
    }

    /** @return array{steps: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['steps' => array_map(static fn (Step $step): array => $step->toArray(), $this->steps)];
    }

    public function find(string $id): ?Step
    {
        foreach ($this->steps as $step) {
            if ($step->id === $id) {
                return $step;
            }
        }

        return null;
    }

    public function indexOf(string $id): ?int
    {
        foreach ($this->steps as $index => $step) {
            if ($step->id === $id) {
                return $index;
            }
        }

        return null;
    }

    public function insert(int $position, Step $step): self
    {
        $steps = $this->steps;
        array_splice($steps, max(0, min($position, count($steps))), 0, [$step]);

        return new self($steps);
    }

    public function replace(Step $step): self
    {
        return new self(array_map(static fn (Step $current): Step => $current->id === $step->id ? $step : $current, $this->steps));
    }

    public function remove(string $id): self
    {
        return new self(array_values(array_filter($this->steps, static fn (Step $step): bool => $step->id !== $id)));
    }

    /** Moves a step to sit before the step now at $position ("+" rows are numbered 0…count). */
    public function move(string $id, int $position): self
    {
        $from = $this->indexOf($id);

        if ($from === null) {
            return $this;
        }

        $step = $this->steps[$from];
        $without = $this->remove($id);

        return $without->insert($position > $from ? $position - 1 : $position, $step);
    }
}
