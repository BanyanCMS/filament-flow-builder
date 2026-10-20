# Filament Flow Builder (working name)

A reusable, card-based approval-flow editor for **Filament v5 / Livewire v4**. It is extracted from Banyan CMS's *Settings → Workflows* editor (Start → steps → Finish, with "+" inserters, per-step outcomes and a settings side panel).

> Status: **design**. No code has moved yet. This README is the spec for the extraction. Licence: **private, all rights reserved** until the owner decides (see Open decisions).

## What it is

Banyan's review-flow editor shows an approval process as a vertical column of cards on a dotted canvas:

- **Start card:** who starts the flow, with chips for its scope ("Page review", "Workflow settings").
- **Step cards:** each step has a type label ("Approval · Step 1"), a name, an assignee summary ("Any 1 of Editors (team, 1 person)"), and its outcomes in colour: "Approve → publish", "Request changes → author". A drag handle and a ⋮ menu sit on each card.
- **"+" inserters** between cards add a step at that place.
- **Finish card:** what happens at the end ("Publish, now or scheduled").
- **Side panel:** the selected card's settings (name, applies-to, approvers, outcomes).
- **Header:** *Test it as…* (simulate the flow as a given person), *More* and *Save*.

The goal: any Filament app can drop this in for its own approval processes (purchase approvals, leave requests, content review) **without Banyan's workflow engine**.

## Proposed package shape

```
src/
  FlowBuilder.php            Filament form field: FlowBuilder::make('flow')
  Pages/EditFlowPage.php     Optional full-page editor (canvas + side panel)
  Data/Flow.php              { start: StartNode, steps: list<Step>, finish: FinishNode }
  Data/Step.php              { id, type, name, assignees, outcomes: list<Outcome>, meta }
  Data/Outcome.php           { key, label, target: 'next'|'finish'|'author'|stepId, tone }
  Contracts/StepType.php     host-registered step types (approval, notify, condition…)
  Contracts/AssigneeSource.php   options + summary text for the "who" picker
  Contracts/FlowSimulator.php    optional: powers "Test it as…"
  Support/FlowValidator.php  unreachable steps, loops, missing assignees → issues list
resources/views/             canvas, card, plus, side-panel (Blade + Alpine)
resources/css/flow-builder.css   Filament theme tokens only (no Banyan tokens)
resources/lang/{en,zh-Hant,zh-Hans}/
```

**The host app owns persistence and meaning.** The plugin edits a plain `Flow` value and validates its shape. Saving it, and turning it into a running process, stay in the host. Banyan keeps its `ReviewFlow` model and engine and maps them with a small adapter.

## Extraction plan

1. **M1, plugin core:** the `Flow`/`Step`/`Outcome` data, the field, the canvas, cards, "+" inserters, drag reorder, the side panel, the validator, and three languages. Pest and Livewire tests, PHPStan level 9.
2. **M2, Banyan adapter:** Banyan's `ReviewFlowEditPage` renders through the plugin. `ReviewFlow` maps to `Flow`, with no change in behaviour (Banyan's existing tests must pass unchanged).
3. **M3, demo and docs:** a standalone Filament demo app and a README with screenshots. Publishing to Packagist is optional.

## Source in Banyan (for M1)

- `packages/admin/src/Workflow/ReviewFlowEditPage.php`, `packages/admin/src/Workflow/ReviewSteps/*`
- `packages/admin/resources/views/workflow/builder*.blade.php`
- `packages/admin/resources/css/builder.css` (≈1,900 lines; to be re-tokenised onto Filament's theme)
- `packages/workflow/src/ReviewFlow/*`: the domain model, which **stays in Banyan** and is only the reference for the generic `Flow`

## Open decisions (owner)

- **Licence:** Banyan is AGPL-3.0 or commercial. Code moved here must be relicensed by its owner. MIT (common for Filament plugins) or proprietary/commercial?
- **Name and home:** `filament-flow-builder` under BanyanCMS, or under another organisation (e.g. Solution Forest)?
- **Scope of M1:** approval steps only, or also condition and notification steps?
