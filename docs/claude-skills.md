# AI Skills (Claude Code & Codex)

The starter kit ships three AI skills that teach any compatible AI coding assistant the kit's conventions, hard rules, and builder APIs. The skills work standalone — they do not require any external tooling or orchestration layer — and are published for both **Claude Code** (`.claude/skills/`) and **OpenAI Codex CLI** (`.codex/skills/`), which reads project-level skills from that directory natively.

## Installation

`php artisan sk:install` copies the skills into your host application's `.claude/skills/` directory and mirrors them to `.codex/skills/` automatically. The `.codex` copies are a **generated mirror**: edit the `.claude` copies (customizations included) and `sk:install`/`sk:update` re-sync the mirror; anything else you place under `.codex/skills/` is never touched.

To skip the skills:

```bash
php artisan sk:install --without-ai-skill
```

The `--without-ai-skill` flag at install time skips both directories, and `sk:update` honors that choice automatically (it never re-adds skipped skills). `sk:update --without-ai-skill` additionally skips regenerating the `.codex/skills/` mirror on a single update run.

## How They Activate

Each skill declares a `description` field that lists the exact files, commands, and symbols that trigger it. Claude Code (and compatible tools) activate the relevant skill automatically when a matching context is detected — no manual invocation needed.

## The Three Skills

### `lvntr-starter-kit` (core)

The entry point. Activates whenever the assistant touches any layer of a starter-kit project: controllers, domain actions, Vue pages, routes, config, lang files, or artisan commands such as `sk:install`, `make:sk-domain`, and `sk:seed-permissions`.

What it enforces:

- **Iron Law and 8 hard rules** — the non-negotiable constraints around `vendor/`, envelope, dialog, URL, and Action patterns
- **Red Flags and Rationalization** — common misuse patterns and how to avoid them
- **Project shape** — where code lives after `sk:install` (your `app/`, `routes/`, `resources/` vs the untouched package in `vendor/`)
- **Command reference** — artisan commands with their flags
- **Permissions and i18n** — role/permission conventions and translation key prefixes
- **Cross-skill routing** — delegates backend detail to `lvntr-kit-domain`, frontend detail to `lvntr-kit-frontend`

### `lvntr-kit-domain` (backend / DDD)

Activates when a task touches `app/Domain/`, controllers under `app/Http/Controllers/Admin/` or `app/Http/Controllers/Api/`, FormRequests, API Resources, Actions, DTOs, Queries, Events, or Listeners, and when symbols such as `to_api`, `ApiResponse`, `BaseAction`, `BaseDTO`, or `ActionPipeline` appear.

What it covers:

- **Controller/Action/DTO/Query recipe** — thin controllers, domain Actions, typed DTOs, query builders
- **API envelope** — `to_api()`, `ApiResponse::*`, `ApiException::*`; never `response()->json()`
- **Route conventions** — typed Wayfinder routes, never closure-based routes
- **Entity scaffold recipe** — step-by-step guide for adding a new domain entity

### `lvntr-kit-frontend` (Vue / builders / composables)

Activates when a task touches `resources/js/pages/Admin/`, `@lvntr/components/*` (SkForm, SkDatatable, SkTabs, AppDialog), or composables such as `useDialog`, `useConfirm`, `useApi`, `useDefinition`, `useRefreshBus`, `useCan`, `useFlash`, `useSidebar`, and `useDarkMode`.

What it covers:

- **FormBuilder (FB)** — `SkForm` field definitions, validation, colSpan, sections, cards, and icons
- **DatatableBuilder (DB)** — `SkDatatable` column definitions, server-side pagination, filters, and row actions
- **TabBuilder (TB)** — `SkTabs` tab definitions and lazy loading
- **Composable reference** — usage contracts for every kit composable
- **Vue recipe** — step-by-step guide for adding a new admin Vue page with form and table

## Notes

- Skills are language-agnostic: they trigger on Turkish keywords as well (for example `yeni domain`, `tablo ekle`, `form ekle`).
- The three skills cross-reference each other; activating one will point the assistant to the others when the scope warrants it.
- If you use neither Claude Code nor Codex, pass `--without-ai-skill` to skip the copy step entirely — no other behavior changes.
- To customize a skill, edit its `.claude/skills/` copy — the `.codex/skills/` mirror picks the change up on the next `sk:install`/`sk:update`. Direct edits under `.codex/skills/` to the three kit skills are overwritten by the mirror.
