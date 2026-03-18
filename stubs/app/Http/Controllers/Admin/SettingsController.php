<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Setting\Actions\SendTestMailAction;
use App\Domain\Setting\Actions\UpdateSettingsAction;
use App\Domain\Setting\DTOs\AuthSettingsDTO;
use App\Domain\Setting\DTOs\GeneralSettingsDTO;
use App\Domain\Setting\DTOs\MailSettingsDTO;
use App\Domain\Setting\DTOs\StorageSettingsDTO;
use App\Domain\Setting\Queries\SettingsDefaultsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\SendTestMailRequest;
use App\Http\Requests\Admin\Settings\UpdateAuthSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateMailSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateStorageSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin panel settings controller.
 *
 * This controller is intentionally thin:
 *   - Validation → FormRequest
 *   - Data mapping → DTO
 *   - Business logic → Action
 *   - Read queries → Query
 */
class SettingsController extends Controller
{
    /**
     * Display the settings page with all groups.
     */
    public function index(SettingsDefaultsQuery $query): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $query->all(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'availableLanguages' => config('app.available_languages', ['en' => 'English']),
        ]);
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(UpdateGeneralSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('general', GeneralSettingsDTO::fromArray($request->validated()));

        return back()->with('success', 'General settings updated.');
    }

    /**
     * Update authentication settings.
     */
    public function updateAuth(UpdateAuthSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('auth', AuthSettingsDTO::fromArray($request->validated()));

        return back()->with('success', 'Authentication settings updated.');
    }

    /**
     * Update mail settings.
     */
    public function updateMail(UpdateMailSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('mail', MailSettingsDTO::fromArray($request->validated()));

        return back()->with('success', 'Mail settings updated.');
    }

    /**
     * Update storage settings.
     */
    public function updateStorage(UpdateStorageSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->execute('storage', StorageSettingsDTO::fromArray($request->validated()));

        return back()->with('success', 'Storage settings updated.');
    }

    /**
     * Send a test email using current mail settings.
     */
    public function testMail(SendTestMailRequest $request, SendTestMailAction $action): RedirectResponse
    {
        try {
            $action->execute($request->input('test_email'));

            return back()->with('success', 'Test email sent successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
