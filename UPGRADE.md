# Upgrading

## From 0.1 to 0.2

Version 0.2 renames some classes and methods. Stored flow data (the JSON), translation keys and CSS classes do not change. Nothing needs a migration.

Search your code for each old name below and replace it.

### Dry run ("Test it as…")

The `Testing` namespace held the "Test it as…" dry run, not test helpers. It is now `DryRun`.

| 0.1 | 0.2 |
|---|---|
| `Testing\FlowWalker` | `DryRun\FlowWalker` |
| `Testing\FlowTestResult` | `DryRun\DryRunResult` |
| `Actions\TestFlowAction` | `Actions\DryRunAction` |
| `TestFlowAction::getDefaultName()` returns `testFlow` | `DryRunAction::getDefaultName()` returns `flowDryRun` |
| `FlowDefinition::test(Flow $flow, array $input)` | `FlowDefinition::dryRun(Flow $flow, array $input)` |
| `FlowDefinition::testSchema()` | `FlowDefinition::dryRunSchema()` |
| `flowIsTestable()` (host page) | `flowHasDryRun()` |
| `<livewire:flow-builder :testable="false" />` | `<livewire:flow-builder :dry-run="false" />` |

If you override `test()` or `testSchema()` in a definition, rename the method. The return type of `dryRun()` is `DryRunResult`.

### Livewire members of `InteractsWithFlowBuilder`

The trait's public members now start with `flow`, so they cannot clash with the members of your page. This matters for `wire:click`, `wire:model`, `$wire.…`, `Livewire::test()->call()` and `->set()`, and for error keys such as `stepData.name`.

| 0.1 | 0.2 |
|---|---|
| `$selected` | `$flowSelected` |
| `$stepData` (and `stepData.…` keys) | `$flowStepData` (and `flowStepData.…` keys) |
| `updatedStepData()` | `updatedFlowStepData()` |
| `select()` | `selectFlowNode()` |
| `insertStep()` | `insertFlowStep()` |
| `removeStep()` | `removeFlowStep()` |
| `moveStep()` | `moveFlowStep()` |
| `moveStepUp()` | `moveFlowStepUp()` |
| `moveStepDown()` | `moveFlowStepDown()` |
| `stepForm()` (schema name) | `flowStepForm()` |
| `settingsForm()` (schema name) | `flowSettingsForm()` |
| `testAction()` and the action name `test` | `flowDryRunAction()` and the action name `flowDryRun` |

These stay the same: `$flow`, `$flowSettings`, `$flowProblems`, `flowDefinitionKey()`, `flowDefinition()`, `flowIsReadOnly()`, `flowChanged()`, `flowFull()`, `flowCanvas()`, `flowCards()`, `flowStartCard()` and the `FLOW_START` and `FLOW_ALWAYS` constants.

If you copied the canvas Blade view, change its `wire:click` calls to the new names. If you include `flow-builder::canvas`, you do not need to do anything.

### Removed

| 0.1 | 0.2 |
|---|---|
| `Data\Condition::stringKeyed()` (deprecated) | Removed. It was internal. |

### `FlowBuilderField::definition()` no longer registers

In 0.1, `FlowBuilderField::make()->definition(MyFlow::class)` registered the class in the app-wide registry if it was missing. That only worked while the form was being built. The builder looks the definition up again on every Livewire request, so a definition registered only by the field could not be found later.

In 0.2 the field does not register anything. If the definition is not registered, `definition()` throws an `InvalidArgumentException` that says how to fix it. Register each definition once on your panel:

```php
->plugin(FlowBuilderPlugin::make()->definitions([MyFlow::class]))
```
