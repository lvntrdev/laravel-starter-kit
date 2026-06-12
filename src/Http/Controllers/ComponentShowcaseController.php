<?php

namespace Lvntr\StarterKit\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Developer-facing showcase of the kit's UI components (PrimeVue + SK).
 *
 * Vendor-resident on purpose: the showcase (this controller, its route file
 * and the SkComponents/* Vue pages) ships inside the package and is never
 * published into the consumer app — it documents the kit, it is not consumer
 * code. The Vue pages resolve through the vendor-pages fallback in the
 * consumer's app.ts (app pages win, vendor pages fill the gaps).
 *
 * Gated by the "system_admin" role in src/routes/sk-components.php, mounted
 * via the moduleRouteRegistry() in StarterKitServiceProvider — outside the
 * dynamic CheckResourcePermission middleware, so it needs no seeded
 * "components.read" permission.
 */
class ComponentShowcaseController extends Controller
{
    public function show(): Response
    {
        // Single tabbed page. The page file is "Show.vue" under SkComponents/ —
        // each tab component keeps the historical "XShowcase" naming because a
        // component whose filename matches a component it renders (<Tag>,
        // <Button>, …) is treated as a recursive self-reference by
        // unplugin-vue-components and crashes the render.
        return Inertia::render('SkComponents/Show');
    }
}
