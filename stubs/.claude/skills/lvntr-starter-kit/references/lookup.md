# Where to look first

> `lvntr-starter-kit` skill'inin referans detayı — gerekince okunur.

If something is unclear, read in this order:

| Want to see | Look at |
|---|---|
| Action / DTO / Query patterns | `app/Domain/User/` (the kit's reference domain) |
| Thin controller template | `app/Http/Controllers/Admin/UserController.php` |
| FormRequest pattern | `app/Http/Requests/Admin/User/StoreUserRequest.php` |
| Resource pattern | `app/Http/Resources/Admin/User/UserResource.php` |
| Event/Listener wiring | `app/Providers/DomainServiceProvider.php` |
| Datatable query helper | `app/Http/Responses/DatatableQueryBuilder.php` |
| ApiResponse / `to_api()` | `app/Http/Responses/ApiResponse.php`, `app/Helpers/sk-helpers.php` |
| Route file template | `routes/web/user-route.php` |
| Permission resource shape | `config/permission-resources.php` |
| Builder source (after `sk:publish`) | `resources/js/components/Lvntr-Starter-Kit/{FormBuilder,DatatableBuilder,TabBuilder}/core/` |
| Composables | `resources/js/composables/` |

If you need to peek inside the package itself, it's at
`vendor/lvntr/laravel-starter-kit/{src,stubs,config}` — read-only.

For external references (online docs, screenshots, full API tables):
**[starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)**.
