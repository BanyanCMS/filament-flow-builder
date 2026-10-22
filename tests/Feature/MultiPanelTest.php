<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\FlowBuilderPlugin;
use Banyan\FilamentFlowBuilder\FlowBuilderServiceProvider;
use Banyan\FilamentFlowBuilder\Livewire\FlowBuilder;
use Banyan\FilamentFlowBuilder\Tests\Support\LeaveFlow;
use Banyan\FilamentFlowBuilder\Tests\Support\LeaveFlowPage;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseFlow;
use Banyan\FilamentFlowBuilder\Tests\Support\PurchaseFlowPage;
use Banyan\FilamentFlowBuilder\Tests\Support\RivalPurchaseFlow;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

covers(FlowBuilderPlugin::class, FlowDefinitionRegistry::class);

/*
 * Two panels on one app ("admin" with the purchase flow, "staff" with the
 * leave flow). The plugin adds no pages or routes, so each panel keeps its own
 * pages and URLs; the flow definitions go into one app-wide registry.
 */

it('keeps each panel\'s pages and routes to itself', function (): void {
    expect(Filament::getPanel('admin')->getPages())->toContain(PurchaseFlowPage::class)->not->toContain(LeaveFlowPage::class)
        ->and(Filament::getPanel('staff')->getPages())->toContain(LeaveFlowPage::class)->not->toContain(PurchaseFlowPage::class)
        ->and(route('filament.admin.pages.purchase-flow'))->toEndWith('/admin/purchase-flow')
        ->and(route('filament.staff.pages.leave-flow'))->toEndWith('/staff/leave-flow')
        ->and(Route::has('filament.staff.pages.purchase-flow'))->toBeFalse()
        ->and(Route::has('filament.admin.pages.leave-flow'))->toBeFalse();
});

it('gives each panel its own plugin instance with its own definitions', function (): void {
    $admin = Filament::getPanel('admin')->getPlugin('flow-builder');
    $staff = Filament::getPanel('staff')->getPlugin('flow-builder');

    expect($admin)->not->toBe($staff)
        ->and($admin->getDefinitions())->toBe([PurchaseFlow::class])
        ->and($staff->getDefinitions())->toBe([LeaveFlow::class]);
});

it('registers every panel\'s definitions in the one shared registry', function (): void {
    $registry = app(FlowDefinitionRegistry::class);

    expect($registry->all())->toHaveKeys(['purchase', 'leave'])
        ->and($registry->get('leave'))->toBeInstanceOf(LeaveFlow::class)
        ->and(app(FlowDefinitionRegistry::class))->toBe($registry);
});

it('builds the page of either panel with its own flow', function (): void {
    Filament::setCurrentPanel('admin');
    Livewire::test(PurchaseFlowPage::class)->assertOk()->assertSee('Staff member submits a purchase')->assertDontSee('asks for leave');

    Filament::setCurrentPanel('staff');
    Livewire::test(LeaveFlowPage::class)->assertOk()->assertSee('Staff member asks for leave')->assertDontSee('submits a purchase');
});

it('does not carry a flow edited in one panel into the other', function (): void {
    $flow = ['steps' => [['id' => 's1', 'type' => 'approval', 'name' => 'Supervisor', 'settings' => ['approvers' => ['supervisor'], 'rule' => 'any', 'count' => 1], 'when' => null]]];

    Filament::setCurrentPanel('staff');
    Livewire::test(LeaveFlowPage::class)->set('data.flow', $flow)->assertSet('data.flow.steps.0.name', 'Supervisor');

    Filament::setCurrentPanel('admin');
    Livewire::test(PurchaseFlowPage::class)->assertSet('data.flow.steps', []);
});

it('lets the stand-alone component name a definition from either panel', function (): void {
    foreach (['admin' => ['purchase', 'Staff member submits a purchase'], 'staff' => ['leave', 'Staff member asks for leave']] as $panel => [$key, $start]) {
        Filament::setCurrentPanel($panel);
        Livewire::test(FlowBuilder::class, ['definition' => $key])->assertOk()->assertSee($start);
    }
});

it('registering the same definition twice, as two panels may, is harmless', function (): void {
    $registry = app(FlowDefinitionRegistry::class);
    $registry->register(PurchaseFlow::class);

    expect($registry->all())->toHaveCount(count(array_unique(array_keys($registry->all()))));
});

it('lets the last class registered under a key win, so a host can swap a definition', function (): void {
    $registry = app(FlowDefinitionRegistry::class);
    $registry->register(RivalPurchaseFlow::class);

    expect($registry->all()['purchase'])->toBe(RivalPurchaseFlow::class)
        ->and($registry->get('purchase'))->toBeInstanceOf(RivalPurchaseFlow::class);
});

it('publishes its views and translations under their own tags', function (): void {
    $views = ServiceProvider::pathsToPublish(FlowBuilderServiceProvider::class, 'flow-builder-views');
    $lang = ServiceProvider::pathsToPublish(FlowBuilderServiceProvider::class, 'flow-builder-translations');

    expect(array_values($views))->toBe([resource_path('views/vendor/flow-builder')])
        ->and(array_keys($views)[0])->toEndWith('resources/views')
        ->and(array_values($lang))->toBe([lang_path('vendor/flow-builder')]);
});

it('uses a published view in place of the package\'s own', function (): void {
    $dir = resource_path('views/vendor/flow-builder/livewire');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/flow-builder.blade.php', '<div>HOST CANVAS</div>');

    try {
        // Rebuild the finder so the override path is picked up, as it is on a fresh request.
        $finder = app('view')->getFinder();
        $finder->flush();
        app('view')->getFinder()->prependNamespace('flow-builder', resource_path('views/vendor/flow-builder'));

        Livewire::test(FlowBuilder::class, ['definition' => 'purchase'])->assertSee('HOST CANVAS');
    } finally {
        @unlink($dir.'/flow-builder.blade.php');
        @rmdir($dir);
        @rmdir(dirname($dir));
        @rmdir(dirname($dir, 2));
    }
});

it('ships the same translation keys in all three languages', function (): void {
    $keys = static fn (array $lines): array => array_keys(Arr::dot($lines));
    $en = require __DIR__.'/../../resources/lang/en/flow.php';

    foreach (['zh-Hant', 'zh-Hans'] as $locale) {
        expect($keys(require __DIR__.'/../../resources/lang/'.$locale.'/flow.php'))->toEqualCanonicalizing($keys($en));
    }
});
