<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Common\Repositories\CustomsStatement\CustomsStatementRepositoryInterface;

class CustomsStatementController extends ApiAdminController
{
    /**
     * OutlayController constructor.
     * @param CustomsStatementRepositoryInterface $repository
     */
    public function __construct(CustomsStatementRepositoryInterface $repository)
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
            'invoice_2_percent' => $model->invoice_2_percent,
            'media' => MediaResource::collection($model->media),
            'notes' => $model->notes
        ]);
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'name','date'];
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
            'date' => 'required|date',
            'amount' => 'required',
            'invoice' => 'nullable',
            'invoice_2_percent' => 'nullable',
            'notes' => 'nullable',
            'media' => 'nullable|array',
        ]);
    }
}
