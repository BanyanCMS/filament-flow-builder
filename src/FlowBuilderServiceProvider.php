<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder;

use Banyan\FlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FlowBuilder\Livewire\FlowBuilder;
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

        Livewire::component('flow-builder', FlowBuilder::class);

        FilamentAsset::register([
            Css::make('flow-builder', __DIR__.'/../resources/css/flow-builder.css'),
        ], 'banyancms/filament-flow-builder');
    }
}
