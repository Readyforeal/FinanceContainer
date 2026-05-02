# Flux UI Migration Design

## Overview

Migrate the entire StewardAI frontend from hand-rolled Tailwind components to Flux UI, the official Livewire component library. Every element that has a Flux equivalent should use Flux. Keep existing ApexCharts, custom progress bars, and chat bubble animations untouched.

## Installation

- `composer require livewire/flux`
- Import all Lucide icons used in the project via `php artisan flux:icon`
- Update `resources/css/app.css` to import Flux CSS and configure dark mode
- Add `@fluxAppearance` to `<head>` and `@fluxScripts` before `</body>` in the app layout
- Remove `tailwind.config.js` if Tailwind v4 with CSS-based config is used, or update it for Flux compatibility

## Icon Strategy

Import all project Lucide icons via `php artisan flux:icon <icon-names>`. After import, icons are used as `flux:icon.home`, `flux:icon.coffee`, etc. The `x-lucide-*` and `x-dynamic-component` icon references throughout the codebase get replaced with `flux:icon.*`.

## Component Mapping

| Current | Flux Replacement |
|---|---|
| Custom sidebar (`livewire/layout/sidebar`) | `flux:sidebar` with `flux:sidebar.item`, `flux:sidebar.nav`, `flux:sidebar.brand` |
| Mobile dock (`livewire/layout/mobile-dock`) | `flux:header` with `flux:navbar` + `flux:sidebar.toggle` (built into sidebar layout) |
| App layout `<main>` | `flux:main` |
| `<h1>`, `<h2>` headings | `flux:heading` with `size` and `level` props |
| `<p>` descriptive text | `flux:text` |
| `<a>` links | `flux:link` |
| `<button>` elements | `flux:button` with `variant` (primary, ghost, danger, subtle) |
| `<input>` elements | `flux:input` with `label`, `type`, `placeholder` props |
| `<select>` elements | `flux:select` + `flux:select.option` |
| `<table>` structures | `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell` |
| Custom modal (categories page) | `flux:modal` with `name` prop |
| Checkbox inputs | `flux:checkbox` |
| Toggle switches | `flux:switch` with `wire:model` |
| `<hr>` / dividers | `flux:separator` |
| Badge `<span>` elements | `flux:badge` with `color`, `size`, `variant` props |
| Dropdown menus | `flux:dropdown` + `flux:menu` + `flux:menu.item` |
| Form field wrappers | `flux:field` + `flux:label` + `flux:error` |
| `<x-lucide-*>` icons | `flux:icon.*` |
| `<x-dynamic-component>` icons | `flux:icon :icon="$variable"` or similar dynamic approach |
| Pagination links | Keep Laravel's default pagination (Flux-compatible styling) |
| Tooltips (title attributes) | `flux:tooltip` |

## What Stays Untouched

- **ApexCharts** -- spending chart component keeps its current implementation
- **Custom progress bars** -- budget progress bars (the colored fill bars) stay as-is
- **Chat bubble CSS** -- custom animations in app.css remain
- **All PHP logic** -- only Blade templates change; no changes to component classes, models, services, or migrations
- **Auth views** -- migrate to Flux components (login, register, forgot password forms)

## Migration Order

1. **Install & configure** -- Composer, CSS, layout directives, icon import
2. **App layout + sidebar + mobile dock** -- `app.blade.php`, sidebar component, mobile dock component
3. **Dashboard** -- balance-cards, budget-progress, flagged-transactions, goals-summary, spending-chart, summary-snippet
4. **Transactions** -- transaction-list (table, filters, bulk actions, inline category select)
5. **Budgets** -- budget-manager (month nav, bucket sections, add/edit form)
6. **Categories** -- category-manager (table, modal with form)
7. **Accounts** -- account-list, add-account, csv-import
8. **Settings** -- budget-ratios, email-recipients, income-sources, sync-schedule
9. **Goals, Summaries, Chat, Profile** -- goal-manager, summary-archive, chat-page, financial-profile
10. **Auth views** -- login, register, forgot-password, reset-password

## Design Decisions

- **Flux sidebar layout**: Use Flux's collapsible sidebar with `collapsible="mobile"` and `sticky`. The mobile dock is replaced by Flux's built-in mobile header with `flux:sidebar.toggle`.
- **Dark mode**: Flux handles dark mode natively via `@fluxAppearance`. Remove the custom theme toggle JS and use Flux's built-in appearance switching.
- **Form inputs**: Every input gets a `label` prop directly on the Flux component rather than separate `<label>` elements, unless custom layout is needed (then use `flux:field` + `flux:label`).
- **Tables**: Use Flux's `flux:table` component tree. Sortable columns use `sortable` prop on `flux:table.column`. The inline category select on transactions stays as a `flux:select` inside a `flux:table.cell`.
- **Modals**: The category edit modal uses `flux:modal` with `name` prop and `wire:flux-modal.open` for triggering.
- **Badges**: Map current badge colors to Flux badge `color` prop values (e.g., `color="yellow"` for review, `color="green"` for OK).
