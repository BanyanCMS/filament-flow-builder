@php
    /** @var array{id: string, number: int, kind: string, name: string, icon: string, color: string, chips: list<string>, outcomes: list<array{label: string, tone: string}>, condition: string|null, problems: list<string>} $card */
    $name = $card['name'] !== '' ? $card['name'] : __('flow-builder::flow.unnamed');
@endphp

<div @class(['ffb__node', 'is-selected' => $this->flowSelected === $card['id'], 'has-problem' => $card['problems'] !== []]) data-testid="flow-card-{{ $card['id'] }}">
    <button type="button" class="ffb__select" wire:click="selectFlowNode({{ \Illuminate\Support\Js::from($card['id']) }})" aria-pressed="{{ $this->flowSelected === $card['id'] ? 'true' : 'false' }}">
        <span class="ffb__head">
            <span class="ffb__icon ffb__icon--{{ $card['color'] }}"><x-filament::icon :icon="$card['icon']" /></span>
            <span>
                <span class="ffb__kind">{{ __('flow-builder::flow.step_kind', ['kind' => $card['kind'], 'number' => $card['number']]) }}</span>
                <span class="ffb__name">{{ $name }}</span>
            </span>
        </span>
        @if ($card['chips'] !== [])
            <span class="ffb__meta">
                @foreach ($card['chips'] as $chip)
                    <span class="ffb__chip">{{ $chip }}</span>
                @endforeach
            </span>
        @endif
        @if ($card['outcomes'] !== [])
            <span class="ffb__outcomes">
                @foreach ($card['outcomes'] as $outcome)
                    <span class="ffb__tone ffb__tone--{{ $outcome['tone'] }}">{{ $outcome['label'] }}</span>
                @endforeach
            </span>
        @endif
        @foreach ($card['problems'] as $problem)
            <span class="ffb__problem">{{ $problem }}</span>
        @endforeach
    </button>

    @unless ($this->flowIsReadOnly())
        <div class="ffb__tools">
            <span class="ffb__drag" aria-hidden="true" title="{{ __('flow-builder::flow.drag', ['name' => $name]) }}">
                <x-filament::icon icon="heroicon-m-bars-2" />
            </span>
            <x-filament::dropdown placement="bottom-end">
                <x-slot name="trigger">
                    <x-filament::icon-button icon="heroicon-m-ellipsis-vertical" color="gray" size="sm" :label="__('flow-builder::flow.card_menu', ['name' => $name])" />
                </x-slot>
                <x-filament::dropdown.list>
                    @unless ($first)
                        <x-filament::dropdown.list.item icon="heroicon-m-arrow-up" wire:click="moveFlowStepUp({{ \Illuminate\Support\Js::from($card['id']) }})">
                            {{ __('flow-builder::flow.move_up') }}
                        </x-filament::dropdown.list.item>
                    @endunless
                    @unless ($final)
                        <x-filament::dropdown.list.item icon="heroicon-m-arrow-down" wire:click="moveFlowStepDown({{ \Illuminate\Support\Js::from($card['id']) }})">
                            {{ __('flow-builder::flow.move_down') }}
                        </x-filament::dropdown.list.item>
                    @endunless
                    <x-filament::dropdown.list.item icon="heroicon-m-trash" color="danger" wire:click="removeFlowStep({{ \Illuminate\Support\Js::from($card['id']) }})" wire:confirm="{{ __('flow-builder::flow.remove_confirm') }}">
                        {{ __('flow-builder::flow.remove') }}
                    </x-filament::dropdown.list.item>
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
    @endunless
</div>
