---
name: fluxui-docs
description: Livewire Flux UI component documentation lookup. Provides offline access to fluxui.dev documentation via CLI. Use when editing Flux UI / Livewire Blade components, needing component props, usage examples, code snippets, layouts, guides, or when user mentions Flux, Flux UI, fluxui, flux:, Blade components.
user-invocable: true
disable-model-invocation: false
---

# Flux UI Docs

Offline Flux UI docs from `fgilio/fluxui-docs-skill`.

## First action

When working with Flux UI components, check local docs before guessing:

```bash
fluxui-docs search <term>
fluxui-docs show <item>
```

Run `fluxui-docs` directly. Never prefix with `bun`, `node`, or `php`.

## Commands

```bash
fluxui-docs docs
fluxui-docs docs --category=components
fluxui-docs docs --json

fluxui-docs search button
fluxui-docs search "date picker"
fluxui-docs search input --json

fluxui-docs show button
fluxui-docs show modal --section=props
fluxui-docs show dropdown --json

fluxui-docs usages subheading
fluxui-docs usages modal.close
fluxui-docs usages button --json

fluxui-docs discover
fluxui-docs discover --json
```

## Use in this project

- Prefer Flux components already used in `resources/views`.
- For Blade edits, query docs for exact component props/slots before changing markup.
- Some components are Pro-only (`pro: true`). Avoid Pro components unless package exists and usage already present.
- Keep accessibility behavior from docs: labels, keyboard patterns, modal/dialog semantics.

## Data

Docs live in `data/`:

- `data/components/`
- `data/layouts/`
- `data/guides/`
- `data/index.json`
- `data/usages.json`

Source: https://github.com/fgilio/fluxui-docs-skill
