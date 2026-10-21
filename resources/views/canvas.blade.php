@php
    /** The canvas and its settings panel; included by any host using InteractsWithFlowBuilder. */
    ['cards' => $cards, 'start' => $start, 'flowDefinition' => $flowDefinition] = $this->flowCanvas();
    $startSelected = $this->selected === $this::FLOW_START;
    $last = count($cards) - 1;
    $selectedCard = collect($cards)->firstWhere('id', $this->selected);
@endphp

<div class="ffb" data-testid="flow-builder" x-data="{ dragging: null, over: null }">
    <section class="ffb__canvas" aria-label="{{ __('flow-builder::flow.canvas') }}">
        @if ($this->flowIsTestable())
            <div class="ffb__toolbar">{{ $this->testAction }}</div>
        @endif

        <ol class="ffb__flow">
            <li class="ffb__item">
                @if ($start['selectable'])
                    <button
                        type="button"
                        wire:click="select({{ \Illuminate\Support\Js::from($this::FLOW_START) }})"
                        @class(['ffb__node', 'ffb__node--fixed', 'ffb__node--button', 'is-selected' => $startSelected, 'has-problem' => $start['problems'] !== []])
                        aria-pressed="{{ $startSelected ? 'true' : 'false' }}"
                        data-testid="flow-start"
                    >
                        @include('flow-builder::livewire.partials.start', ['start' => $start])
                    </button>
                @else
                    <div class="ffb__node ffb__node--fixed" data-testid="flow-start">
                        @include('flow-builder::livewire.partials.start', ['start' => $start])
                    </div>
                @endif
            </li>

            @foreach ($cards as $index => $card)
                @include('flow-builder::livewire.partials.plus', ['position' => $index, 'after' => $index === 0 ? __('flow-builder::flow.start_kind') : $cards[$index - 1]['name']])

                <li
                    class="ffb__item"
                    wire:key="ffb-{{ $card['id'] }}"
                    @unless ($this->flowIsReadOnly())
                        draggable="true"
                        x-on:dragstart="dragging = {{ \Illuminate\Support\Js::from($card['id']) }}; $event.dataTransfer.effectAllowed = 'move'"
                        x-on:dragend="dragging = null; over = null"
                    @endunless
                    x-bind:class="{ 'is-dragging': dragging === {{ \Illuminate\Support\Js::from($card['id']) }} }"
                >
                    @if ($card['condition'] !== null)
                        <div class="ffb__condition">
                            <span class="ffb__icon ffb__icon--warning"><x-filament::icon icon="heroicon-m-arrows-right-left" /></span>
                            {{ $card['condition'] }}
                        </div>
                        <div class="ffb__branch" data-testid="flow-branch-{{ $card['id'] }}">
                            <span aria-hidden="true"></span>
                            <div class="ffb__lane">
                                <span class="ffb__lane-label ffb__lane-label--yes">{{ __('flow-builder::flow.yes') }}</span>
                                @include('flow-builder::livewire.partials.card', ['card' => $card, 'first' => $index === 0, 'final' => $index === $last])
                            </div>
                            <div class="ffb__lane ffb__lane--no">
                                <span class="ffb__lane-label ffb__lane-label--no">{{ __('flow-builder::flow.no') }}</span>
                                <span class="ffb__skip">{{ __('flow-builder::flow.skipped') }}</span>
                            </div>
                            <span class="ffb__merge" aria-hidden="true"></span>
                        </div>
                    @else
                        @include('flow-builder::livewire.partials.card', ['card' => $card, 'first' => $index === 0, 'final' => $index === $last])
                    @endif
                </li>
            @endforeach

            @include('flow-builder::livewire.partials.plus', ['position' => count($cards), 'after' => $cards === [] ? __('flow-builder::flow.start_kind') : $cards[$last]['name']])

            <li class="ffb__item">
                <div class="ffb__node ffb__node--fixed" data-testid="flow-finish">
                    <span class="ffb__head">
                        <span class="ffb__icon ffb__icon--success"><x-filament::icon icon="heroicon-m-flag" /></span>
                        <span>
                            <span class="ffb__kind">{{ __('flow-builder::flow.finish_kind') }}</span>
                            <span class="ffb__name">{{ $flowDefinition->finishLabel() }}</span>
                        </span>
                    </span>
                </div>
            </li>
        </ol>
    </section>

    <aside class="ffb__panel" aria-label="{{ __('flow-builder::flow.panel.label') }}" data-testid="flow-panel">
        @if ($this->flowIsReadOnly() && ($startSelected || is_array($selectedCard)))
            <p class="ffb__readonly">{{ __('flow-builder::flow.read_only') }}</p>
        @endif

        @if ($startSelected)
            <p class="ffb__kind">{{ __('flow-builder::flow.start_kind') }}</p>
            <h2 class="ffb__panel-title">{{ $flowDefinition->settingsLabel() }}</h2>

            @foreach ($start['problems'] as $problem)
                <p class="ffb__problem">{{ $problem }}</p>
            @endforeach

            {{ $this->settingsForm }}
        @elseif (is_array($selectedCard))
            <p class="ffb__kind">{{ $selectedCard['kind'] }}</p>
            <h2 class="ffb__panel-title">{{ $selectedCard['name'] !== '' ? $selectedCard['name'] : __('flow-builder::flow.unnamed') }}</h2>

            @foreach ($selectedCard['problems'] as $problem)
                <p class="ffb__problem">{{ $problem }}</p>
            @endforeach

            {{ $this->stepForm }}

            @if ($selectedCard['outcomes'] !== [])
                <div class="ffb__outcomes-panel">
                    <p class="ffb__label">{{ __('flow-builder::flow.panel.outcomes') }}</p>
                    @foreach ($selectedCard['outcomes'] as $outcome)
                        <p class="ffb__out"><span class="ffb__tone ffb__tone--{{ $outcome['tone'] }}">{{ $outcome['label'] }}</span></p>
                    @endforeach
                </div>
            @endif

            @unless ($this->flowIsReadOnly())
                <x-filament::button
                    color="danger"
                    outlined
                    size="sm"
                    icon="heroicon-m-trash"
                    wire:click="removeStep({{ \Illuminate\Support\Js::from($selectedCard['id']) }})"
                    wire:confirm="{{ __('flow-builder::flow.remove_confirm') }}"
                    data-testid="flow-remove"
                >
                    {{ __('flow-builder::flow.remove') }}
                </x-filament::button>
            @endunless
        @else
            <p class="ffb__help">{{ $this->flowIsReadOnly() ? __('flow-builder::flow.read_only') : __('flow-builder::flow.panel.empty') }}</p>
        @endif
    </aside>

</div>
