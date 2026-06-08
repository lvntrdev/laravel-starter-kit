<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Developer-facing showcase of the kit's UI components (PrimeVue + SK).
 *
 * Gated by the "system_admin" role in routes/web/components-route.php. That
 * route file is excluded from the dynamic CheckResourcePermission middleware,
 * so it does not require a seeded "components.read" permission.
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
