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
    public function tag(): Response
    {
        // NOTE: the page file is intentionally NOT named "Tag.vue" — a component
        // whose filename matches a tag it uses (<Tag>) is treated as a recursive
        // self-reference by unplugin-vue-components, which crashes the render with
        // "Maximum call stack size exceeded". Keep this name decoupled from <Tag>.
        return Inertia::render('Admin/Components/TagShowcase');
    }

    public function buttons(): Response
    {
        // Same filename-collision caveat as index(): the page is "ButtonShowcase",
        // never "Button.vue", so it does not self-reference the <Button> it renders.
        return Inertia::render('Admin/Components/ButtonShowcase');
    }

    public function messages(): Response
    {
        // Same filename-collision caveat: the page is "MessageShowcase", never
        // "Message.vue", so it does not self-reference the <Message> it renders.
        return Inertia::render('Admin/Components/MessageShowcase');
    }

    public function toast(): Response
    {
        // Same filename-collision caveat: the page is "ToastShowcase", never
        // "Toast.vue", so it does not self-reference the <Toast> it renders.
        return Inertia::render('Admin/Components/ToastShowcase');
    }

    public function forms(): Response
    {
        // FormBuilder (FB / SkForm) live showcase — every field type plus the
        // card / aside / divided-horizontal / filter-bar layout compositions.
        // Page is "FormShowcase", never "Form.vue", so it does not collide with
        // any auto-imported component name.
        return Inertia::render('Admin/Components/FormShowcase');
    }
}
