<?php

namespace App\Http\Controllers\Admin;

use App\Domain\SampleContent\Actions\CreateSampleContentAction;
use App\Domain\SampleContent\Actions\DeleteSampleContentAction;
use App\Domain\SampleContent\Actions\UpdateSampleContentAction;
use App\Domain\SampleContent\DTOs\SampleContentDTO;
use App\Domain\SampleContent\Queries\SampleContentDatatableQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SampleContent\StoreSampleContentRequest;
use App\Http\Requests\Admin\SampleContent\UpdateSampleContentRequest;
use App\Http\Resources\Admin\SampleContent\SampleContentResource;
use App\Http\Responses\ApiResponse;
use App\Models\SampleContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SampleContentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/SampleContents/Index');
    }

    public function dtApi(SampleContentDatatableQuery $query): ApiResponse
    {
        return $query->response();
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SampleContents/Create');
    }

    public function store(StoreSampleContentRequest $request, CreateSampleContentAction $action): RedirectResponse
    {
        $dto = SampleContentDTO::fromArray([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $action->execute($dto);

        return back()->with('success', __('sample-contents.messages.created'));
    }

    public function data(SampleContent $sample_content): ApiResponse
    {
        $sample_content->loadMissing('user:id,first_name,last_name');

        return to_api(['sample_content' => new SampleContentResource($sample_content)]);
    }

    public function edit(SampleContent $sample_content): Response
    {
        $sample_content->loadMissing('user:id,first_name,last_name');

        return Inertia::render('Admin/SampleContents/Edit', [
            'sample_content' => new SampleContentResource($sample_content),
        ]);
    }

    public function update(UpdateSampleContentRequest $request, SampleContent $sample_content, UpdateSampleContentAction $action): RedirectResponse
    {
        $dto = SampleContentDTO::fromArray([
            ...$request->validated(),
            'user_id' => $sample_content->user_id, // preserve original author
        ]);

        $action->execute($sample_content, $dto, $request->user()->id);

        return redirect()
            ->route('sample-contents.index')
            ->with('success', __('sample-contents.messages.updated'));
    }

    public function destroy(Request $request, SampleContent $sample_content, DeleteSampleContentAction $action): RedirectResponse
    {
        try {
            $action->execute($sample_content, $request->user()->id);

            return redirect()
                ->route('sample-contents.index')
                ->with('success', __('sample-contents.messages.deleted'));
        } catch (\LogicException $e) {
            return redirect()
                ->route('sample-contents.index')
                ->with('error', $e->getMessage());
        }
    }
}
