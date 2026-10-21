@php
    /** The Start card's content: what sets the flow going, and its own settings at a glance. */
    /** @var array{selectable: bool, chips: list<string>, problems: list<string>} $start */
@endphp

<span class="ffb__head">
    <span class="ffb__icon ffb__icon--gray"><x-filament::icon icon="heroicon-m-play" /></span>
    <span>
        <span class="ffb__kind">{{ __('flow-builder::flow.start_kind') }}</span>
        <span class="ffb__name">{{ $flowDefinition->startLabel() }}</span>
    </span>
</span>
@if ($start['chips'] !== [])
    <span class="ffb__meta">
        @foreach ($start['chips'] as $chip)
            <span class="ffb__chip">{{ $chip }}</span>
        @endforeach
    </span>
@endif
@foreach ($start['problems'] as $problem)
    <span class="ffb__problem">{{ $problem }}</span>
@endforeach
