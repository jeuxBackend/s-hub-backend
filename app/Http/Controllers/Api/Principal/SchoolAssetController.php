<?php

namespace App\Http\Controllers\Api\Principal;

use App\Actions\SchoolAsset\DeleteSchoolAssetAction;
use App\Actions\SchoolAsset\StoreSchoolAssetAction;
use App\Actions\SchoolAsset\UpdateSchoolAssetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolAsset\StoreSchoolAssetRequest;
use App\Http\Requests\SchoolAsset\UpdateSchoolAssetRequest;
use App\Http\Resources\SchoolAssetResource;
use App\Models\SchoolAsset;
use Throwable;

class SchoolAssetController extends Controller
{
    /**
     * The school's stamp and watermark (at most one of each).
     */
    public function index()
    {
        try {
            $assets = SchoolAsset::where('institution_id', auth()->user()->institution_id)->get();

            return $this->successResponse(
                SchoolAssetResource::collection($assets),
                'School assets retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show(SchoolAsset $schoolAsset)
    {
        try {
            $this->assertInScope($schoolAsset);

            return $this->successResponse(
                new SchoolAssetResource($schoolAsset),
                'School asset retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(StoreSchoolAssetRequest $request, StoreSchoolAssetAction $action)
    {
        try {
            $user = auth()->user();
            $data = $request->validated();

            $alreadyExists = SchoolAsset::where('institution_id', $user->institution_id)
                ->where('type', $data['type'])
                ->exists();

            if ($alreadyExists) {
                return $this->errorResponse(
                    'This school already has a ' . $data['type'] . '. Use update instead.',
                    422
                );
            }

            $data['institution_id'] = $user->institution_id;
            $data['created_by'] = $user->id;

            $asset = $action->handle($data);

            return $this->successResponse(
                new SchoolAssetResource($asset),
                ucfirst($data['type']) . ' created successfully',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(UpdateSchoolAssetRequest $request, SchoolAsset $schoolAsset, UpdateSchoolAssetAction $action)
    {
        try {
            $this->assertInScope($schoolAsset);

            $asset = $action->handle($schoolAsset, $request->validated());

            return $this->successResponse(
                new SchoolAssetResource($asset),
                'School asset updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(SchoolAsset $schoolAsset, DeleteSchoolAssetAction $action)
    {
        try {
            $this->assertInScope($schoolAsset);

            $action->handle($schoolAsset);

            return $this->successResponse(null, 'School asset deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Abort with 404 if this asset doesn't belong to the authenticated
     * Principal/SchoolAdmin's own institution.
     */
    private function assertInScope(SchoolAsset $asset): void
    {
        if ($asset->institution_id !== auth()->user()->institution_id) {
            abort(404);
        }
    }
}
