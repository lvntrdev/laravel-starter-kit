<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Developer-facing showcase of the kit's UI components (PrimeVue + SK).
 *
 * Gated behind the "components.read" permission (derived from the route name
 * "components.index" by the CheckResourcePermission middleware). Seed it with
 * `php artisan sk:seed-permissions`. Only system_admin receives it by default.
 */
class ComponentShowcaseController extends Controller
{
    public function index(): Response
    {
        // NOTE: the page file is intentionally NOT named "Tag.vue" — a component
        // whose filename matches a tag it uses (<Tag>) is treated as a recursive
        // self-reference by unplugin-vue-components, which crashes the render with
        // "Maximum call stack size exceeded". Keep this name decoupled from <Tag>.
        return Inertia::render('Admin/Components/TagShowcase');
    }
}
