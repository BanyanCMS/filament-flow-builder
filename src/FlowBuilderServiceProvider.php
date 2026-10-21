<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Livewire\FlowBuilder;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FlowBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FlowDefinitionRegistry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flow-builder');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'flow-builder');
        $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/flow-builder')], 'flow-builder-translations');

        // An app may boot this provider without Livewire (e.g. a headless or console-only host).
        if ($this->app->bound('livewire')) {
            Livewire::component('flow-builder', FlowBuilder::class);
        }

        FilamentAsset::register([
            Css::make('flow-builder', __DIR__.'/../resources/css/flow-builder.css'),
        ], 'banyancms/filament-flow-builder');
    }
}
