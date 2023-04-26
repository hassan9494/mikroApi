<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Common\Repositories\Slide\SlideRepositoryInterface;

class SlideController extends ApiAdminController
{

    /**
     * SlideRepositoryInterface constructor.
     * @param SlideRepositoryInterface $repository
     */
    public function __construct(SlideRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'name' => $model->name,
            'order' => $model->order,
            'media' => MediaResource::collection($model->media),
        ]);
    }

    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
        $model->syncMedia($data['media'] ?? []);
        return $this->success(
            $model
        );
    }


    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
        $model->syncMedia($data['media'] ?? []);
        return $this->success(
            $model
        );
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'order' => 'required',
            'name' => 'required|max:255',
            'media' => 'nullable|array',
        ]);
    }

}
