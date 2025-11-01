<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Shop\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;

class PaymentMethodController extends ApiAdminController
{

    /**
     * OutlayController constructor.
     * @param PaymentMethodRepositoryInterface $repository
     */
    public function __construct(PaymentMethodRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'name' => $model->name,
            'commission_type' => $model->commission_type,
            'commission' => $model->commission,
            'commission_range' => $model->commission_range
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
        return $this->success(
            $model
        );
    }

    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
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
            'commission_type' => 'required',
            'commission' => 'nullable',
        ]);
    }

}
