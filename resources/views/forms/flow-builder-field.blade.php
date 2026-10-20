<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <livewire:flow-builder
        wire:model="{{ $getStatePath() }}"
        :definition="$getDefinitionKey()"
        :read-only="$isDisabled()"
        :wire:key="$getLivewireKey()"
    />
</x-dynamic-component>
