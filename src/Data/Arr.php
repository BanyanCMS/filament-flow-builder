<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Data;

/** @internal */
final class Arr
{
    /**
     * Anything that is not an array becomes empty. Every key becomes a string.
     *
     * @return array<string, mixed>
     */
    public static function stringKeyed(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }
}
