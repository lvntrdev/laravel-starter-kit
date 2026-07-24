# Command reference

> Reference detail for the `lvntr-starter-kit` skill — read on demand.

### Lifecycle

```bash
php artisan sk:install                # one-time setup (interactive)
php artisan sk:install --force        # re-run installer, overwriting non-preservable files
php artisan sk:install --resume       # resume an interrupted install (checkpointed per step)
php artisan sk:install --without-eject     # keep User/Role runtime in vendor (skip default eject)
php artisan sk:install --without-ai-skill  # skip publishing the AI skills (.claude/skills + .codex/skills)

php artisan sk:update                 # upgrade kit files; preserves your customizations
php artisan sk:update --dry-run       # preview what would change  ← ALWAYS first
php artisan sk:update --force         # ignore hash registry; overwrite everything

php artisan sk:upgrade                # only for projects upgrading Laravel 12 → 13 (asserts PHP 8.4)
```

### Health check

```bash
php artisan sk:doctor                 # environment/config/queue/schedule checks
php artisan sk:doctor --json          # machine-readable output (used by the admin UI)
php artisan sk:doctor --only=database,redis
```

### Domain scaffolding

```bash
# Interactive wizard — prompts for fields, layers, soft-deletes, vue mode
php artisan make:sk-domain Product

# Non-interactive (CI/scripting)
php artisan make:sk-domain Product \
    --fields="name:string,price:decimal,status:string" \
    --id-type=ulid \
    --admin --api --events \
    --soft-deletes \
    --vue=full --vue-fields

# Opt-in extras: Policy, Factory, Seeder, Pest test, Eloquent relations
php artisan make:sk-domain Product --with=policy,factory,seeder,test
php artisan make:sk-domain Product --with-relations            # interactive relation wizard
php artisan make:sk-domain Product --relations="belongsTo:User,hasMany:Comment"

# Parse fields from an existing migration instead of typing them
php artisan make:sk-domain Product --from-migration=2026_03_21_create_products_table.php

# Reverse it (also strips route entries and provider registrations)
php artisan remove:sk-domain Product
```

### Ejecting vendor domains (`sk:eject`)

Kit domain runtimes live in the vendor package and resolve through `class_alias`;
a local copy in `app/` always wins. To take full, project-owned control of a domain:

```bash
php artisan sk:eject User             # copy vendor domain into app/Domain/User + rewrite namespace
php artisan sk:eject Role --dry-run   # print the copy/rewrite/injection plan, write nothing
php artisan sk:eject Setting --no-vue # backend only; leave Vue pages untouched
php artisan sk:eject Media --force    # overwrite existing app files (backend + Vue pages)
```

Ejectable domains: `User`, `Role`, `Setting`, `ActivityLog`, `ApiClient`,
`SystemHealth`, `ContentLanguage`, `Definitions`, `MediaUpload`, `ApiRoute`,
`Logs`, `Files`, `Session`, `Media`.

**Trade-off:** an ejected domain no longer receives upstream fixes via
`composer update`. Fresh installs auto-eject `User` and `Role` (opt out with
`sk:install --without-eject`).

### Permissions

```bash
php artisan sk:seed-permissions --fresh   # re-seed roles + permissions after editing
                                          # config/permission-resources.php
```

### Customization

```bash
# Publish optional assets — opens an interactive picker
php artisan sk:publish

# Or pass tags directly:
#   components | datatable | form | tabs | skeleton | ui
#   filemanager | composables | plugins | lang | config | helpers
php artisan sk:publish --tag=form --tag=datatable --force
```

Once you publish a component to `resources/js/components/Lvntr-Starter-Kit/`
(or a composable to `resources/js/composables/`), edit it freely — the local
copy overrides the vendor one, and `sk:update` skips it (the hash registry
detects your modifications).

### Day-to-day

```bash
php artisan site:install              # one-shot: migrate + seed + passport keys + admin
php artisan env:sync                  # propagate .env keys → .env.example (also runs in pre-commit)
php artisan wayfinder:generate        # regenerate @/routes and @/actions after route changes
php artisan file-manager:purge-trash  # permanently delete expired file-manager trash (--days=N)
composer dev                          # serve + queue + pail + vite (concurrent)
```
