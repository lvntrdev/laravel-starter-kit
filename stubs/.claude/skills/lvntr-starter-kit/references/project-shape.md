# Project shape after `sk:install`

> `lvntr-starter-kit` skill'inin referans detayı — gerekince okunur.

```
app/
├── Domain/
│   ├── {Entity}/{Actions,DTOs,Queries,Events,Listeners}    # your business logic
│   └── Shared/{Actions,Contracts,DTOs,Pipelines,Services}  # kit-provided base classes
├── Enums/{PermissionEnum,RoleEnum,…}.php                   # kit + your enums
├── Http/
│   ├── Controllers/{Admin,Api}/{Entity}Controller.php      # thin controllers
│   ├── Requests/{Admin,Api}/{Entity}/…Request.php          # FormRequests
│   ├── Resources/{Admin,Api}/{Entity}/{Entity}Resource.php
│   ├── Middleware/{CheckResourcePermission,SecurityHeaders,AssignTraceId}.php
│   └── Responses/{ApiResponse,DatatableQueryBuilder}.php   # SAFE_UPDATE — don't customize
├── Helpers/sk-helpers.php                                  # to_api(), format_date(), …
├── Models/                                                 # Eloquent models live here
└── Traits/{HasActivityLogging,HasMediaCollections}.php     # kit traits

config/
├── starter-kit.php                                         # published — editable
├── permission-resources.php                                # YOU edit; declares resources
├── settings.php                                            # settings panel groups
└── activitylog.php                                         # spatie/laravel-activitylog

routes/
├── web.php / api.php                                       # orchestrators (require children)
├── web/{entity}-route.php                                  # one file per resource
└── api/{entity}-route.php

resources/
├── js/
│   ├── components/Lvntr-Starter-Kit/                       # ONLY exists if sk:publish ran
│   ├── composables/                                        # useDialog, useConfirm, useApi…
│   ├── pages/Admin/**                                      # your Vue pages
│   └── plugins/permission.ts                               # v-can / v-role directives
└── css/theme/                                              # Tailwind 4 + PrimeVue PT theme

lang/
├── en/sk-*.php                                             # kit translation namespaces
└── tr/sk-*.php

stubs/, src/                                                # do NOT exist in your app
storage/starter-kit/hashes.json                             # sk:update tracking — auto-managed
```

The package source is at `vendor/lvntr/laravel-starter-kit/{src,stubs,config}` —
read it if you want to understand internals, but never edit it.
