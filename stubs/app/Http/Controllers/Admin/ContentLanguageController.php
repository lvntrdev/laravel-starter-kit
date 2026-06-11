<?php

namespace App\Http\Controllers\Admin;

use App\Domain\ContentLanguage\Actions\CreateContentLanguageAction;
use App\Domain\ContentLanguage\Actions\DeleteContentLanguageAction;
use App\Domain\ContentLanguage\Actions\UpdateContentLanguageAction;
use App\Domain\ContentLanguage\DTOs\ContentLanguageDTO;
use App\Domain\ContentLanguage\Queries\ContentLanguageDatatableQuery;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentLanguage\StoreContentLanguageRequest;
use App\Http\Requests\Admin\ContentLanguage\UpdateContentLanguageRequest;
use App\Http\Resources\Admin\ContentLanguage\ContentLanguageResource;
use App\Http\Responses\ApiResponse;
use App\Models\ContentLanguage;
use Illuminate\Http\Request;

/**
 * Admin panel content-language management controller.
 *
 * Content languages drive the locale tabs of translatable content fields
 * (TranslatableInput) — a separate concept from the admin UI locale, which
 * stays bound to lang files via config('app.languages').
 *
 * This controller is intentionally thin:
 *   - Validation → FormRequest
 *   - Data mapping → DTO
 *   - Business logic (single-default invariant, last-default guard) → Action
 *   - Listing / filtering → Query
 *
 * Every action returns through the API envelope (to_api() / ApiResponse) so the
 * datatable + dialog frontend (useApi) consumes a single, consistent shape;
 * Action-thrown ApiException::unprocessable() guards (delete/deactivate the last
 * default) surface as a 422 with a localised message through the same envelope.
 *
 * Authorization: content languages are a setting, so there is NO dedicated
 * permission resource — whoever can see/update Settings can see/update content
 * languages. The routes carry explicit middleware (check.permission:settings.read
 * for reads, settings.update for writes — see content-language-route.php).
 * store()/update() re-enforce settings.update inside their FormRequest; the
 * read/destroy endpoints re-check here as defense in depth so the action stays
 * protected even if the route were misconfigured.
 */
class ContentLanguageController extends Controller
{
    /**
     * Return paginated content languages as JSON for the DataTable component.
     */
    public function dtApi(Request $request, ContentLanguageDatatableQuery $query): ApiResponse
    {
        abort_unless($request->user()?->can('settings.read') ?? false, 403);

        return $query->response();
    }

    /**
     * Store a newly created content language.
     */
    public function store(StoreContentLanguageRequest $request, CreateContentLanguageAction $action): ApiResponse
    {
        $language = $action->execute(ContentLanguageDTO::fromArray($request->validated()));

        return to_api(
            new ContentLanguageResource($language),
            __('sk-message.created', ['entity' => __('sk-content-languages.entity')]),
        )->status(201);
    }

    /**
     * Return a single content language as JSON for dialog (edit form) usage.
     */
    public function data(Request $request, ContentLanguage $contentLanguage): ApiResponse
    {
        abort_unless($request->user()?->can('settings.read') ?? false, 403);

        return to_api(new ContentLanguageResource($contentLanguage));
    }

    /**
     * Update the specified content language.
     *
     * @throws ApiException When the change would strip the last active default.
     */
    public function update(
        UpdateContentLanguageRequest $request,
        ContentLanguage $contentLanguage,
        UpdateContentLanguageAction $action,
    ): ApiResponse {
        $updated = $action->execute($contentLanguage, ContentLanguageDTO::fromArray($request->validated()));

        return to_api(
            new ContentLanguageResource($updated),
            __('sk-message.updated', ['entity' => __('sk-content-languages.entity')]),
        );
    }

    /**
     * Remove the specified content language.
     *
     * @throws ApiException When attempting to delete the default language.
     */
    public function destroy(
        Request $request,
        ContentLanguage $contentLanguage,
        DeleteContentLanguageAction $action,
    ): ApiResponse {
        abort_unless($request->user()?->can('settings.update') ?? false, 403);

        $action->execute($contentLanguage);

        return to_api(
            message: __('sk-message.deleted', ['entity' => __('sk-content-languages.entity')]),
        );
    }
}
