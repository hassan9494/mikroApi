<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;

class OutlayController extends ApiAdminController
{

    /**
     * OutlayController constructor.
     * @param OutlayRepositoryInterface $repository
     */
    public function __construct(OutlayRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'name' => $model->name,
            'amount' => $model->amount,
            'date' => $model->date,
            'invoice' => $model->invoice,
            'media' => MediaResource::collection($model->media),
            'notes' => $model->notes,
            'type' => $model->type,
            'sub_type' => $model->sub_type,
            'total_amount' => $model->total_amount,
            'tax' => $model->tax
        ]);
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'name','sub_type'];
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
            'name' => 'required|max:255',
            'type' => 'required',
            'date' => 'required|date',
            'amount' => 'required',
            'total_amount' => 'required',
            'tax' => 'nullable',
            'sub_type' => 'required',
            'invoice' => 'nullable',
            'notes' => 'nullable',
            'media' => 'nullable|array',
        ]);
    }

}
