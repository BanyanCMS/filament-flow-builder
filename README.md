# Filament Flow Builder

**[READ ONLY]** Subtree split of the `filament-flow-builder` package from [BanyanCMS/banyan](https://github.com/BanyanCMS/banyan).

A visual, card-based **workflow builder for Filament v5**. Use it for approval chains, review steps, sign-offs and any process that moves an item through steps.

**You** define in PHP which steps and conditions a flow may use. **Your users** then design their own flows in the panel. They add steps with "+", drag them into order, add conditions, set reminders and try a flow out before saving it. A small runner then moves real items through a saved flow and fires events. Notifications, tasks and side effects stay in your app.

![The builder: Start, four steps (one of them conditional), Finish, and the settings panel of the selected step](docs/images/builder.png)

It is a standalone Filament plugin. It needs no other Banyan package and has no database tables of its own. A flow is plain data that you store in a JSON column. It was extracted from [Banyan CMS](https://github.com/BanyanCMS/banyan), whose *Settings → Workflows* runs on it. Licensed **MIT**.

> **Building a real process?** Read the **[usage guide](docs/guide.md)**. It walks through a complete, tested example (purchase requests). It covers matching approvers to your users, status, Approve and Request changes, "any / all / a number / every team" rules, what happens when something fails midway, the audit log, notifications and reminders.

> **Where to change it:** the source lives in the Banyan monorepo at `packages/filament-flow-builder`. This repository is its published split. Open issues and pull requests against the [monorepo](https://github.com/BanyanCMS/banyan).

---

## Contents

- [What you get](#what-you-get)
- [Inspiration and how it compares](#inspiration-and-how-it-compares)
- [Requirements](#requirements)
- [Install](#install)
- [Quick start: an approval flow in five files](#quick-start-an-approval-flow-in-five-files)
- [The editor, screen by screen](#the-editor-screen-by-screen)
- [Defining a flow](#defining-a-flow)
- [Step types](#step-types)
- [Conditions](#conditions)
- [Running a flow (the API)](#running-a-flow-the-api)
- [Two ways to put the builder on a page](#two-ways-to-put-the-builder-on-a-page)
- [A real example: purchase requests](#a-real-example-purchase-requests)
- [Data model and where flow data lives](#data-model-and-where-flow-data-lives)
- [Using it in more than one panel](#using-it-in-more-than-one-panel)
- [Customising views and translations](#customising-views-and-translations)
- [Look and feel, dark mode, languages](#look-and-feel-dark-mode-languages)
- [Security](#security)
- [Reference](#reference)
- [Developing the plugin](#developing-the-plugin)
- [Roadmap](#roadmap)
- [Licence](#licence)

---

## What you get

| | |
|---|---|
| **Canvas** | Start and Finish cards, a "+" between every pair of steps, drag-and-drop or Move up/down to reorder, and a summary on each card (who, how many, reminder, outcomes). |
| **Settings panel** | The selected step's settings: any Filament fields you declare. |
| **Step types in code** | Built in: *Approval* (approvers, any or all or a number, reminder) and *Parallel review* (several teams at once). Add your own by extending one class. |
| **Presets** | One step type can offer several entries under "+", for example one ready-made step per department. |
| **Conditions** | "Only when …" on any step, drawn as a Yes/No branch. The built-in *A value matches* compares a value you provide with what the designer typed. Add your own. |
| **Flow settings** | Settings of the whole flow, on the Start card. |
| **Validation** | A step's problems ("Choose at least one approver.") show in red on its card, and the field will not save a flow that has any. |
| **Test it as…** | Fill in an example item and see which steps it passes, which it skips and where it ends, without saving. |
| **Runner** | `start()` and `decide()` move an item through a saved flow and fire `StepReached`, `StepSkipped`, `OutcomeChosen`, `FlowReturned` and `FlowFinished`. |
| **Theming** | It uses the panel's own colours, so it follows your theme and dark mode. |
| **Languages** | English, Traditional Chinese and Simplified Chinese, publishable. |

## Inspiration and how it compares

### Where the ideas come from

The plugin was built for Banyan, a CMS for government, public bodies and schools. These organisations need approval before content goes live, and they want the people who own the process to change it without a developer. The design borrows from these tools:

- **Drupal Workflows and Content Moderation.** An item moves through states, and the site decides who may move it. Here the steps and rules live in a flow that an administrator edits.
- **Umbraco workflow add-ons.** Approval groups and a chain of approvers for content. Here the groups are plain keys that your app maps to real users.
- **Visual flow editors such as Power Automate, Jira workflows and n8n.** A canvas of cards, a "+" to add a step, and a settings panel for the selected card. The builder uses the same pattern, with the step types written in PHP.
- **BPMN, kept very small.** It has approval tasks, a parallel review and a "send back" path. A step can need one approver, all of them, a number of them, or one person from each team. A flow is one straight list of steps. It has no free-form branches, loops or timers. A condition only skips a step.

Reading the code, this is what it actually does:

- A flow is an ordered list of steps between a fixed Start and Finish.
- Step types and condition types are PHP classes. Designers can only pick from the ones your definition allows.
- *Approval* has the rules `any`, `all` and `count`. *Parallel review* waits for every team it lists. Your own step types declare their own outcomes.
- The runner fires `StepReached`, `StepSkipped`, `OutcomeChosen`, `FlowReturned` and `FlowFinished`. It does not count votes. Your app does that.

The main idea is that **the designer edits a flow, and your app keeps all the facts**. The plugin does not store items, votes, users or logs.

### How it differs from other approval and workflow packages

All of these solve different problems. This summary comes from each project's public page as of October 2026. Check those pages for current details.

| | **This plugin** | [Approvals](https://filamentphp.com/plugins/eightynine-approvals) (Eighty Nine) | [laravel-process-approval](https://github.com/ringlesoft/laravel-process-approval) (RingleSoft) | [FFHS IT Approvals](https://filamentphp.com/plugins/ffhs-it-approvals) | [Flow](https://filamentphp.com/plugins/packstub-flow) (Packstub) | [Workflow Engine](https://filamentphp.com/plugins/leek-workflow-engine) (Leek) |
| --- | --- | --- | --- | --- | --- | --- |
| **What it is** | A card-based flow editor for approval chains, plus a small runner | Approval flows for Filament models | Multi-level approval workflows for Eloquent models | Approval workflows for Filament models | Workflow automation on a canvas, with an approval action | Workflow automation for Filament |
| **Licence** | MIT | MIT | MIT | MIT | MIT | Commercial (listed at $69) |
| **Visual builder** | Yes, cards with "+" and drag-and-drop | Not listed. Flows and steps come from an admin menu | Not listed. Flows and steps are managed with artisan commands | Not listed. Flows are defined in PHP | Yes, a drag-and-drop canvas | Yes, a drag-and-drop builder |
| **Who designs flows** | Your users, in the panel | Admins, in the panel | Developers, with artisan commands | Developers, in code | Users, in the panel | Users, in the panel |
| **Approval rules** | Any one, all, a number, or one per team | Any user in the step's role. "All" is not listed | Each step has an action type, such as approve or check | Roles, specific users or permissions | An "Ask for approval" action with Approved, Rejected and Timed out outputs | Not listed |
| **Conditions** | "Only when" on a step, with your own condition types | Not listed | Not listed | Not listed | True/False branches | True/False branches |
| **Return for changes** | Built in (Request changes goes to the start) | Not listed | Submit, approve, reject, return and discard actions | Not listed | Not listed | Not listed |
| **Storage** | None. You store the flow as JSON and keep the position yourself | Built on laravel-process-approval | Its own tables (four migrations) | Not listed | Waits are stored as database rows | Not listed |
| **History** | Your events and your table. The example has an audit log | A history view | Relations for approvals | Not listed | Versions of each workflow | Run history with step logs |
| **Filament versions** | 5 | 3 (no v5 listed) | Not listed | 3 and 4 (no v5 listed) | 4 and 5 | 4 and 5 |

**When to choose another package.**

- You want approvals on a model with no flow editor for your users. Approvals or FFHS IT Approvals fit this.
- You want a ready-made approval table, buttons and history without writing your own workflow service. This plugin gives you the editor and the runner. You write the rest.
- You need automation such as triggers, HTTP calls, schedules or queued actions. Packstub Flow and Leek Workflow Engine fit this.
- You want state transitions on a model, such as draft to published. A state-machine package fits better.

**When to choose this one.**

- Your users, not developers, must design and change the approval chain.
- You need rules such as "any one", "all", "a number" or "one per team".
- You want to keep your own users, roles, status and audit log, and use the plugin only for the flow.
- You need Filament 5 and a permissive MIT licence.

## Requirements

- PHP 8.3 or newer
- Laravel 13
- Filament 5
- Livewire 4

## Install

```bash
composer require banyancms/filament-flow-builder
php artisan filament:assets
```

The service provider is discovered automatically. `filament:assets` publishes the builder's stylesheet. Run it again after you update the package.

There are no migrations and no config file.

---

## Quick start: an approval flow in five files

This builds a complete example in a fresh Laravel + Filament app: an **Approval flows** resource whose form contains the builder. The screenshots on this page come from this app.

### 1. Define what a flow may contain

`app/Flows/PurchaseFlow.php`

```php
<?php

namespace App\Flows;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;
use Banyan\FilamentFlowBuilder\Steps\ParallelApprovalStep;

class PurchaseFlow extends FlowDefinition
{
    public static function key(): string
    {
        return 'purchase';
    }

    // What "+" offers.
    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class, ParallelApprovalStep::class, BudgetCheckStep::class];
    }

    public function startLabel(): string
    {
        return 'Staff member submits a purchase';
    }

    public function finishLabel(): string
    {
        return 'Order placed';
    }

    // Who an Approval step can ask.
    public function approvers(): array
    {
        return ['manager' => 'Line manager', 'finance' => 'Finance team', 'director' => 'Director'];
    }

    // Which teams a Parallel review can ask.
    public function teams(): array
    {
        return ['finance' => 'Finance team', 'it' => 'IT team', 'legal' => 'Legal'];
    }

    // What conditions and "Test it as…" can look at. Your app supplies the values when it runs a flow.
    public function contextFields(): array
    {
        return ['amount' => 'Amount (HKD)', 'department' => 'Department'];
    }
}
```

### 2. Add a step type of your own

`app/Flows/BudgetCheckStep.php`

```php
<?php

namespace App\Flows;

use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\AbstractStepType;
use Filament\Forms\Components\TextInput;

class BudgetCheckStep extends AbstractStepType
{
    public static function key(): string { return 'budget-check'; }
    public function label(): string { return 'Budget check'; }
    public function description(): string { return 'Confirm the budget code has room for it.'; }
    public function icon(): string { return 'heroicon-m-banknotes'; }
    public function color(): string { return 'info'; }

    // The settings panel: any Filament fields.
    public function schema(FlowDefinition $definition): array
    {
        return [TextInput::make('budget_code')->label('Budget code')->live(onBlur: true)];
    }

    // Chips on the card.
    public function summary(Step $step, FlowDefinition $definition): array
    {
        return filled($step->settings['budget_code'] ?? null) ? ['Code '.$step->settings['budget_code']] : [];
    }

    // The ways out of this step, and where each one goes.
    public function outcomes(Step $step, FlowDefinition $definition): array
    {
        return [
            new Outcome('funded', 'Funded', Outcome::NEXT, 'success'),
            new Outcome('no-budget', 'No budget', Outcome::START, 'danger'),
        ];
    }

    // Problems are shown on the card and stop the form from saving.
    public function validate(Step $step, FlowDefinition $definition): array
    {
        return blank($step->settings['budget_code'] ?? null) ? ['Give the budget code.'] : [];
    }
}
```

### 3. Register the plugin on your panel

`app/Providers/Filament/AdminPanelProvider.php`

```php
use App\Flows\PurchaseFlow;
use Banyan\FilamentFlowBuilder\FlowBuilderPlugin;

return $panel
    // …
    ->plugin(FlowBuilderPlugin::make()->definitions([PurchaseFlow::class]));
```

### 4. A model with a JSON column

```php
// database/migrations/xxxx_create_approval_flows_table.php
Schema::create('approval_flows', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->json('flow');
    $table->timestamps();
});
```

```php
// app/Models/ApprovalFlow.php
class ApprovalFlow extends Model
{
    protected $fillable = ['name', 'flow'];

    protected $casts = ['flow' => 'array'];
}
```

### 5. Put the builder in a form

```php
use App\Flows\PurchaseFlow;
use Banyan\FilamentFlowBuilder\Forms\FlowBuilderField;
use Filament\Forms\Components\TextInput;

public static function form(Schema $schema): Schema
{
    return $schema->columns(1)->components([
        TextInput::make('name')->required(),
        FlowBuilderField::make('flow')->label('Approval flow')->definition(PurchaseFlow::class),
    ]);
}
```

That is all. Create a record, and the builder appears in the form:

![The resource's edit page with the builder in the form](docs/images/edit-page.png)

---

## The editor, screen by screen

### Adding a step

Every "+" between two cards opens the step types your definition allows, and their presets.

![The "+" menu](docs/images/add-step.png)

### A step's settings

Select a card to open its settings. For the built-in Approval step these are the name, the approvers, how many must approve, a reminder, and "Only when …". The panel also shows where each outcome leads.

<img src="docs/images/step-panel.png" alt="The settings panel of an approval step" width="352">

### Conditions

A step with a condition is drawn as a branch. **Yes** runs the step. **No** skips it.

### Flow settings on the Start card

When your definition declares `settingsSchema()`, the Start card becomes clickable and opens the flow's own settings. `startSummary()` puts chips on the card.

![Flow settings on the Start card](docs/images/flow-settings.png)

### Problems

Each problem shows in red on its card. The form will not save until they are fixed.

![A step with a problem](docs/images/problems.png)

### Test it as…

This is the builder's dry run. The button above the canvas asks for your `contextFields()` and walks the flow as it is on screen. It shows each step the item passes, each it skips, and where it ends. Nothing is saved.

![Test it as…](docs/images/test-it-as.png)

### Dark mode

![Dark mode](docs/images/dark.png)

---

## Defining a flow

A **flow definition** is a class that extends `FlowDefinition`. Only `key()` is required. Everything else has a default.

```php
class PurchaseFlow extends FlowDefinition
{
    public static function key(): string { return 'purchase'; }
}
```

Register each definition once, through the plugin (`FlowBuilderPlugin::make()->definitions([...])`), or directly on the registry. `FlowBuilderField::make()->definition(PurchaseFlow::class)` does not register anything. If the class is not registered, it throws an `InvalidArgumentException` that says how to register it. The builder looks the definition up again on every Livewire request, so it must be registered before any page loads.

```php
app(\Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry::class)->register(PurchaseFlow::class);
```

What a definition can say:

| Method | Default | What it is for |
|---|---|---|
| `key()` (static, required) | | A stable name. It is not stored with the flow, but fields and pages refer to it. |
| `label()` | the key as a headline (`purchase` → "Purchase") | A name for people. |
| `stepTypeClasses()` (protected) | `[ApprovalStep::class]` | The step types "+" offers. |
| `conditionTypeClasses()` (protected) | `[ValueCondition::class]` | The conditions "Only when" offers. Return `[]` for none. |
| `startLabel()`, `finishLabel()` | "Someone starts the flow", "Done" | Text on the fixed cards. |
| `approvers()` | `[]` | `key => label` the Approval step can choose from. |
| `teams()` | `[]` | `key => label` the Parallel review can choose from. |
| `contextFields()` | `[]` | `key => label` of the values your app knows about an item. Used by *A value matches* and by "Test it as…". |
| `settingsSchema()` | `[]` | Filament fields for the whole flow. If not empty, the Start card opens them. |
| `startSummary(array $settings)` | `[]` | Chips on the Start card. |
| `validateSettings(array $settings)` | `[]` | Problems with the flow settings, shown on the Start card. |
| `dryRunSchema()` | one text field per context field | The form of "Test it as…". |
| `dryRun(Flow $flow, array $input)` | walks the flow on screen | Override to run your own simulator. Returns a `DryRunResult`. |
| `maxSteps()` | 30 | "+" stops offering steps beyond this. |
| `newStepId()` | a random id | Override to match ids your app already stores. |
| `settingsLabel()` | "Flow settings" | The heading of the Start card's panel. |
| `returnLabel()` | "back to the start" | What an outcome that goes back to the start says. |

Read-only helpers: `stepTypes()`, `stepType($key)`, `conditionTypes()`, `conditionType($key)`.

## Step types

### Built in

| Class | Key | Settings | Outcomes |
|---|---|---|---|
| `ApprovalStep` | `approval` | `approvers` (from `approvers()`), `rule` (`any`, `all` or `count`), `count`; takes a reminder | Approve (next), Request changes (back to the start) |
| `ParallelApprovalStep` | `parallel-approval` | `teams` (from `teams()`; starts with the first two) | All teams approve (next), Request changes (back to the start) |

### Your own

Extend `AbstractStepType` and override what you need (see [the quick start](#2-add-a-step-type-of-your-own)). The full contract is `StepType`:

| Method | Default in `AbstractStepType` | What it is for |
|---|---|---|
| `key()` (static) | | Stored in each step's `type`. Never change it once flows exist. |
| `label()`, `description()`, `icon()`, `color()` | | How "+" lists it. `color()` is a Filament colour name. |
| `defaults($definition)` | `[]` | The settings a new step starts with. |
| `schema($definition)` | `[]` | Filament fields of the settings panel. Their state is the step's `settings`. Use `->live()` or `->live(onBlur: true)` so the card updates as people type. |
| `summary($step, $definition)` | `[]` | Chips on the card. |
| `outcomes($step, $definition)` | one outcome, *Done*, to the next step | The ways out. `new Outcome($key, $label, Outcome::NEXT \| Outcome::START \| Outcome::FINISH, $tone)` |
| `validate($step, $definition)` | `[]` | Problems shown on the card. Any problem stops the field from saving. |
| `presets($definition)` | one, from `defaults()` | Several entries under "+": `new StepPreset($key, $label, $description, $icon, name: …, settings: …, when: …, reminderDays: …)`. |
| `cardKind()`, `cardIcon()`, `cardColor()` | label, icon, colour | Let one step type look different by its settings. |
| `remindable()` | `false` | `true` adds "Remind after … days" to the panel, saved as `reminder_days`. |

**Presets** example: one ready-made budget check per department.

```php
public function presets(FlowDefinition $definition): array
{
    return [
        new StepPreset('it', 'IT budget check', 'IT-01, reminder after 3 days', 'heroicon-m-computer-desktop',
            name: 'IT budget', settings: ['budget_code' => 'IT-01'], reminderDays: 3),
        new StepPreset('marketing', 'Marketing budget check', 'MK-01', null,
            name: 'Marketing budget', settings: ['budget_code' => 'MK-01']),
    ];
}
```

## Conditions

"Only when …" is set per step. The built-in `ValueCondition` (key `value`, shown as *A value matches*) compares one of your `contextFields()` with what the designer typed. It supports *is*, *is not*, *is more than*, *is less than* and *contains*. Numbers are compared as numbers.

Your own condition implements `ConditionType`:

```php
use Banyan\FilamentFlowBuilder\Conditions\ConditionType;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Filament\Forms\Components\Select;

class DepartmentIs implements ConditionType
{
    public static function key(): string { return 'department'; }
    public function label(): string { return 'Department is…'; }

    public function schema(FlowDefinition $definition): array
    {
        return [Select::make('department')->options(['it' => 'IT', 'hr' => 'HR'])->live()];
    }

    // The sentence on the branch.
    public function describe(array $settings, FlowDefinition $definition): string
    {
        return 'Only when the department is '.($settings['department'] ?? '…');
    }

    // Decided by the runner with the context your app passes in.
    public function passes(array $settings, array $context, FlowDefinition $definition): bool
    {
        return ($context['department'] ?? null) === ($settings['department'] ?? null);
    }

    public function validate(array $settings, FlowDefinition $definition): array
    {
        return blank($settings['department'] ?? null) ? ['Choose a department.'] : [];
    }

    // What a step starts with when this condition is chosen.
    public function defaults(FlowDefinition $definition): array
    {
        return ['department' => 'it'];
    }
}
```

List it in your definition's `conditionTypeClasses()`.

---

## Running a flow (the API)

The builder edits flows. `FlowRunner` runs them. It is stateless: you keep where an item is (a `Position`) and pass it back on the next decision. It works the same from a controller, a job or a command, with or without the panel.

```php
use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Runtime\FlowRunner;
use Banyan\FilamentFlowBuilder\Runtime\Position;

$runner = app(FlowRunner::class);
$definition = new PurchaseFlow;
$flow = Flow::fromArray($approvalFlow->flow);                  // the JSON column
$context = ['amount' => $purchase->amount, 'department' => $purchase->department];

// Submit: the item goes to the first step whose condition holds.
$position = $runner->start($definition, $flow, $context);
$purchase->update(['flow_position' => $position->toArray()]);

// Later, someone acts on the step it is at.
$position = $runner->decide($definition, $flow, Position::fromArray($purchase->flow_position), 'approve', $context);
$purchase->update(['flow_position' => $position->toArray()]);

$position->state;    // Position::AT_STEP, Position::RETURNED or Position::FINISHED
$position->stepId;   // the step it is at, when AT_STEP
```

`decide()` refuses an outcome key the step does not have, and a position that is not at a step.

### Events

Listen to these for notifications, tasks, audit and side effects. Each carries the definition key, the `Flow` and your `$context`.

| Event | When | Also carries |
|---|---|---|
| `Runtime\Events\StepReached` | An item arrives at a step: notify its approvers, create tasks | `$step` |
| `Runtime\Events\StepSkipped` | A step's condition did not hold | `$step` |
| `Runtime\Events\OutcomeChosen` | Someone approved, rejected, … | `$step`, `$outcome` |
| `Runtime\Events\FlowReturned` | An outcome sent it back to the start | `$from` (the step) |
| `Runtime\Events\FlowFinished` | Every step is done | |

```php
use Banyan\FilamentFlowBuilder\Runtime\Events\StepReached;

Event::listen(function (StepReached $event): void {
    $approvers = $event->step->settings['approvers'] ?? [];
    // Notify them, create a task, remind after $event->step->reminderDays days, …
});
```

Walk the example flow above with an amount of HKD 25,000. It fires `StepReached` and `OutcomeChosen` for each of the four steps, in order, then `FlowFinished`. With HKD 5,000, the director's step is `StepSkipped` instead.

---

## Two ways to put the builder on a page

**1. As a form field** (the quick start). The state is the flow as an array. The field validates on save and lists every problem on its card. Use it when the flow belongs to a record, such as a template, a category or a department.

```php
FlowBuilderField::make('flow')->definition(PurchaseFlow::class)
```

**2. On your own page or Livewire component**, with the `InteractsWithFlowBuilder` trait. Use it when the page has its own saving, checks or header actions around the canvas, as Banyan's *Settings → Workflows* does.

```php
use Banyan\FilamentFlowBuilder\Concerns\InteractsWithFlowBuilder;
use Filament\Pages\Page;

class EditPurchaseFlow extends Page
{
    use InteractsWithFlowBuilder;

    protected string $view = 'filament.pages.edit-purchase-flow';

    /** @var array<string, mixed> */
    public array $flow = ['steps' => []];

    public bool $unsaved = false;

    public function flowDefinitionKey(): string
    {
        return PurchaseFlow::key();
    }

    public function save(): void
    {
        // Problems your own code finds, keyed by step id (or "start"), show in red on those cards.
        $this->flowProblems = MyFlowStore::save($this->flow);
        $this->unsaved = false;
    }

    // Optional hooks
    protected function flowChanged(): void { $this->unsaved = true; }        // after every change
    protected function flowFull(int $max): void { /* "+" on a full flow */ }
    public function flowIsReadOnly(): bool { return false; }
    public function flowHasDryRun(): bool { return true; }                    // show "Test it as…"
}
```

```blade
{{-- resources/views/filament/pages/edit-purchase-flow.blade.php --}}
<x-filament-panels::page>
    @include('flow-builder::canvas')
</x-filament-panels::page>
```

The trait adds these public members to your page. They all contain `flow`, so they do not clash with your own.

| Member | Use |
|---|---|
| `$flowSelected` | The selected card: a step id, `FLOW_START`, or `null`. |
| `$flowStepData` | The selected step's panel state. |
| `$flowSettings` | The flow's own settings, edited on the Start card. |
| `$flowProblems` | Problems your code found, by step id (or `"start"`). |
| `selectFlowNode()`, `insertFlowStep()`, `removeFlowStep()`, `moveFlowStep()`, `moveFlowStepUp()`, `moveFlowStepDown()` | What the canvas calls. |
| `flowStepForm()`, `flowSettingsForm()`, `flowDryRunAction()` | The panel schemas and the "Test it as…" action. |

A page that needs a definition built with context (the current tenant, a record id) can override `flowDefinition()` and return its own instance. The stand-alone Livewire component `Banyan\FilamentFlowBuilder\Livewire\FlowBuilder` (`<livewire:flow-builder definition="purchase" wire:model="flow" />`) uses the same trait.

---

## A real example: purchase requests

[`examples/PurchaseRequests`](examples/PurchaseRequests) is a complete, tested app module built on the plugin:

- administrators design approval flows;
- staff submit purchase requests;
- managers, teams and directors approve or ask for changes;
- every move is logged.

The [usage guide](docs/guide.md) explains it piece by piece.

![A request's status and its audit log](docs/images/example-history.png)

| Question | Where the guide answers it |
|---|---|
| How do approver keys match the users of my system? | [§3](docs/guide.md#3-matching-approvers-to-the-users-of-your-system) |
| How does the item's status follow the flow? | [§4](docs/guide.md#4-status-from-where-it-is-in-the-flow-to-what-users-see) |
| How do Approve and Request changes work? What about "all of them", "a number" and parallel teams? | [§5](docs/guide.md#5-actions-how-approve-and-request-changes-work) |
| Who may act, and how do I enforce it? | [§6](docs/guide.md#6-control-who-may-do-what) |
| What if it fails midway: nobody can act, the flow changed, it waits too long? | [§7](docs/guide.md#7-when-things-go-wrong-in-the-middle-of-a-flow) |
| How do I keep an audit log? | [§8](docs/guide.md#8-the-audit-log) |

---

## Data model and where flow data lives

### What the package owns

Nothing in the database. The plugin has no migrations, no models and no config file. It adds no pages, resources or routes.

Your app owns all the data:

| What | Where it lives |
|---|---|
| The flow a person designed | A JSON column of your own table (`approval_flows.flow` in the example) |
| Where an item is in a flow | A JSON column on the item (`purchase_requests.position`) |
| Votes, status, history, reminders | Your own tables |
| Who holds an approver key | Your users, roles or any directory you choose |

Because the data is yours, you can back it up, migrate it, index it and query it like the rest of your app.

### How a flow is stored

This is what the field saves and what `Flow::fromArray()` reads. It is plain JSON, so you can version it, diff it, export it or seed it.

```json
{
  "steps": [
    {
      "id": "step_k3j9x0q2ab",
      "type": "approval",
      "name": "Line manager approval",
      "settings": { "approvers": ["manager"], "rule": "any", "count": 1 },
      "when": null,
      "reminder_days": 2
    },
    {
      "id": "step_p0w8e7r6ty",
      "type": "approval",
      "name": "Director approval",
      "settings": { "approvers": ["director"], "rule": "any", "count": 1 },
      "when": { "type": "value", "settings": { "field": "amount", "operator": "greater_than", "value": "10000" } },
      "reminder_days": null
    }
  ],
  "settings": { "title": "Office purchases", "urgent": true }
}
```

| Key | Meaning |
|---|---|
| `steps[].id` | Unique in the flow. Letters, numbers, `_` and `-`, up to 64 characters. A step with a bad or missing id gets a new one when it is read |
| `steps[].type` | The step type `key()`, for example `approval`. Required |
| `steps[].name` | The name the designer typed |
| `steps[].settings` | The settings of the step type. `approvers`, `rule` and `count` for `approval` |
| `steps[].when` | `null`, or `{ "type": …, "settings": … }` for a condition |
| `steps[].reminder_days` | `null`, or 1 to 365 |
| `settings` | The flow's own settings, from `settingsSchema()` |

Reading is tolerant. Broken steps are dropped and duplicate ids are renamed, so an old or hand-edited value never breaks the editor. `Flow` is immutable. It has `find()`, `indexOf()`, `insert()`, `replace()`, `remove()`, `move()`, `withSettings()` and `toArray()`. `FlowValidator::problems($flow, $definition)` returns the same problems the editor shows, by step id.

### How a position is stored

`Position` is two values: a state and a step id. You store `$position->toArray()` on your item and restore it with `Position::fromArray()`.

```json
{ "state": "step", "step_id": "step_k3j9x0q2ab" }
```

| `state` | Meaning | `step_id` |
|---|---|---|
| `step` | Waiting at that step | The step id |
| `returned` | An outcome sent it back to the start | `null` |
| `finished` | Every step is done | `null` |

`fromArray()` never throws. A value it cannot read, or a `step` with no id, becomes `returned`.

Snapshot the flow on the item when it starts (the example does this in `purchase_requests.flow`). Then a later edit of the template does not move items that are already in it.

### Querying from your code

The queries below use the example's tables (see `examples/PurchaseRequests/database/migrations`). Change the names to match yours.

```php
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\PurchaseRequest;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\PurchaseRequestWorkflow;

$workflow = app(PurchaseRequestWorkflow::class);

// Who can act on this request now (user ids). The requester is left out.
$userIds = $workflow->whoCanAct($request);

// May this user act on it?
$allowed = $workflow->canAct($request, auth()->id());

// The step it waits at, read from the request's own snapshot of the flow.
$step = $workflow->stepOf($request);   // ?Step

// Requests waiting at one step. Use the step id inside the position JSON.
$waiting = PurchaseRequest::query()
    ->where('status', PurchaseRequest::IN_REVIEW)
    ->where('position->step_id', $stepId)
    ->get();

// Requests nobody can act on, and requests that have waited more than three days.
$stuck = PurchaseRequest::query()->where('status', PurchaseRequest::STUCK)->get();
$slow = PurchaseRequest::query()
    ->where('status', PurchaseRequest::IN_REVIEW)
    ->where('step_reached_at', '<', now()->subDays(3))
    ->get();

// History, oldest first, and the votes on one step.
$lines = $request->history;
$votes = $request->votes()->where('step_id', $stepId)->get();
```

To find the requests a person can act on, read the keys they hold (`ApproverDirectory::keysOf($userId)`) and check each waiting request with `canAct()`. The step's keys live in the step's `settings` (`approvers`, or `teams` for a parallel review), so they are not a column you can filter on in SQL.

Write the position only through the runner's result. Do not build a `Position` by hand, so that the events and your status stay in step.

## Using it in more than one panel

You can add the plugin to as many Filament panels as you like. The plugin adds no pages, resources or routes. A panel only contributes its list of flow definitions.

```php
// AdminPanelProvider
->plugin(FlowBuilderPlugin::make()->definitions([PurchaseFlow::class]))

// StaffPanelProvider
->plugin(FlowBuilderPlugin::make()->definitions([LeaveFlow::class]))
```

What is per panel:

- **The plugin instance and its definitions list.** Each panel has its own `FlowBuilderPlugin`. `FlowBuilderPlugin::getDefinitions()` returns the classes listed for that panel.
- **Your pages and routes.** The builder is a form field or a trait that you put on your own pages, so each panel keeps its own pages and URLs, for example `/admin/purchase-flow` and `/staff/leave-flow`.

What is shared by the whole app:

- The `FlowDefinitionRegistry` singleton. It holds the definition class names by key.
- The Livewire `flow-builder` component, the views, the translations, the stylesheet and the `flow-builder::` view namespace.

This means:

- A definition registered in one panel can be used in another panel. For example, `<livewire:flow-builder definition="leave" />` works on a page in the `admin` panel.
- Flow data never crosses panels by itself. It lives in your form state and your database. A flow edited in one panel does not show in the other unless both pages read the same record.
- Registering the same definition class twice, as two panels may, does no harm.
- **Do not reuse a definition key for different classes in two panels.** The last class registered under a key wins. This is also how you replace a definition with your own subclass of it.

If a panel needs different permissions, check the panel in your own page or policy, for example with `Filament::getCurrentPanel()?->getId()`. The plugin does not add any Gate abilities.

The tests in `tests/Feature/MultiPanelTest.php` cover two panels on one app.

## Customising views and translations

The plugin has no pages to subclass. You change it in three ways:

- Put `FlowBuilderField` or the `InteractsWithFlowBuilder` trait on your own pages and resources (see [Two ways to put the builder on a page](#two-ways-to-put-the-builder-on-a-page)).
- Override `flowDefinition()` on your page to build a definition with context.
- Publish the views or the translations.

Publish tags:

| Tag | Files go to |
|---|---|
| `flow-builder-views` | `resources/views/vendor/flow-builder` |
| `flow-builder-translations` | `lang/vendor/flow-builder` |

```bash
php artisan vendor:publish --tag=flow-builder-views
php artisan vendor:publish --tag=flow-builder-translations
```

Both tags are only available in the console. There is no config file to publish.

A view in `resources/views/vendor/flow-builder` wins over the package's own. The views are:

| View | What it draws |
|---|---|
| `canvas.blade.php` | The canvas (`@include('flow-builder::canvas')`) |
| `forms/flow-builder-field.blade.php` | The form field |
| `livewire/flow-builder.blade.php` | The stand-alone component |
| `livewire/partials/start.blade.php`, `plus.blade.php`, `card.blade.php` | The Start card, the "+" and a step card |

Publish only what you change and delete the rest, so that updates to the other views still reach you.

Translations are read as `flow-builder::flow.*`. The files land in `lang/vendor/flow-builder/{locale}/flow.php`. The three languages have the same keys.

## Look and feel, dark mode, languages

- **Colours** come from the panel's palette (`--primary-*`, `--gray-*`, `--success-*`, …), so the builder follows your theme and dark mode with no work. To fine-tune it, override the `--ffb-*` custom properties on `.ffb`. They are defined at the top of `resources/css/flow-builder.css`. Green text uses `--ffb-success-text` to keep 4.5:1 contrast.
- **Languages:** English, Traditional Chinese (`zh-Hant`) and Simplified Chinese (`zh-Hans`). Publish the translations to change the wording:

```bash
php artisan vendor:publish --tag=flow-builder-translations
```

- **Accessibility:** every card, "+" and menu item is a real button. Moving a step also works from each card's menu (Move up/down), not only by dragging.

## Security

To report a security problem, read [SECURITY.md](SECURITY.md). Please report it privately and do not open a public issue.

## Reference

| Class | Role |
|---|---|
| `FlowBuilderPlugin` | Filament plugin. `definitions([...])` registers definitions on a panel. |
| `Definitions\FlowDefinition`, `Definitions\FlowDefinitionRegistry` | What a flow may contain. The registry looks them up by key. |
| `Forms\FlowBuilderField` | The form field. `definition(string $classOrKey)`. |
| `Concerns\InteractsWithFlowBuilder` | The canvas on any Livewire component. |
| `Livewire\FlowBuilder` | Stand-alone Livewire component. |
| `Steps\StepType`, `Steps\AbstractStepType`, `Steps\ApprovalStep`, `Steps\ParallelApprovalStep` | Step types. |
| `Conditions\ConditionType`, `Conditions\ValueCondition` | Conditions. |
| `Data\Flow`, `Data\Step`, `Data\Condition`, `Data\Outcome`, `Data\StepPreset` | The data. |
| `Validation\FlowValidator` | Problems by step id. |
| `Runtime\FlowRunner`, `Runtime\Position`, `Runtime\Events\*` | Running a flow. |
| `Actions\DryRunAction`, `DryRun\FlowWalker`, `DryRun\DryRunResult` | "Test it as…" (the dry run). |

## Developing the plugin

The package carries its own Testbench app:

```bash
composer install
composer serve      # http://127.0.0.1:8090/admin/purchase-flow, the workbench panel
composer test       # Pest
composer analyse    # PHPStan level 9
```

The workbench panel (`workbench/`) uses the `PurchaseFlow` and `BudgetCheckStep` from `tests/Support`.

## Roadmap

1. **M1: the plugin.** ✅ Data, field, canvas, drag, conditions, validator, runner, events, three languages.
2. **M2: Banyan runs on it.** ✅ Parallel review, presets, reminders, flow settings, "Test it as…", the host trait. Banyan's *Settings → Workflows* uses the plugin.
3. **M3: release.** A Packagist release and more built-in step types if useful (for example notify-only).

## Licence

MIT. See [LICENSE](LICENSE).
