@php
    /** The "+" between two cards: add a step here, or drop a dragged card here. */
    /** @var int $position */
    /** @var string $after */
@endphp

<li
    class="ffb__plus-row"
    data-testid="flow-plus-{{ $position }}"
    @unless ($this->readOnly)
        x-on:dragover.prevent="if (dragging) over = {{ $position }}"
        x-on:dragleave="over = null"
        x-on:drop.prevent="if (dragging) { $wire.moveStep(dragging, {{ $position }}); dragging = null; over = null }"
        x-bind:class="{ 'is-over': over === {{ $position }} }"
    @endunless
>
    <span class="ffb__link"></span>
    @unless ($this->readOnly)
        <x-filament::dropdown placement="right-start" width="xs">
            <x-slot name="trigger">
                <button type="button" class="ffb__plus" aria-label="{{ __('flow-builder::flow.add_after', ['name' => $after]) }}">
                    <x-filament::icon icon="heroicon-m-plus" />
                </button>
            </x-slot>
            <x-filament::dropdown.list>
                @foreach ($flowDefinition->stepTypes() as $key => $type)
                    <x-filament::dropdown.list.item :icon="$type->icon()" wire:click="insertStep({{ $position }}, {{ \Illuminate\Support\Js::from($key) }})">
                        <span class="ffb__menu-label">{{ $type->label() }}</span>
                        @if ($type->description() !== '')
                            <span class="ffb__menu-help">{{ $type->description() }}</span>
                        @endif
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
        <span class="ffb__link"></span>
    @endunless
</li>
