# Command reference

> `lvntr-starter-kit` skill'inin referans detayı — gerekince okunur.

### Lifecycle

```bash
php artisan sk:install                # one-time setup (interactive)
php artisan sk:install --force        # re-run installer, overwriting non-preservable files

php artisan sk:update                 # upgrade kit files; preserves your customizations
php artisan sk:update --dry-run       # preview what would change
php artisan sk:update --force         # ignore hash registry; overwrite everything

php artisan sk:upgrade                # only for projects upgrading Laravel 12 → 13
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

# Parse fields from an existing migration instead of typing them
php artisan make:sk-domain Product --from-migration=2026_03_21_create_products_table.php

# Reverse it (also strips route entries and provider registrations)
php artisan remove:sk-domain Product
```

### Permissions

```bash
php artisan sk:seed-permissions --fresh   # re-seed roles + permissions after editing
                                          # config/permission-resources.php
```

### Customization

```bash
# Publish optional assets — opens an interactive picker
php artisan sk:publish

# Or pass tags directly: components | datatable | form | tabs | skeleton
#                       ui | lang | config | helpers
php artisan sk:publish --tag=form --tag=datatable --force
```

Once you publish a component to `resources/js/components/Lvntr-Starter-Kit/`,
edit it freely — `sk:update` will skip it (the hash registry detects your
modifications).

### Day-to-day

```bash
php artisan site:install              # one-shot: migrate + seed + passport keys + admin
php artisan env:sync                  # propagate .env keys → .env.example (also runs in pre-commit)
php artisan wayfinder:generate        # regenerate @/routes and @/actions after route changes
composer dev                          # serve + queue + pail + vite (concurrent)
```
