<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Admin\Http\Resources\TransactionResource;
use Modules\Shop\Http\Resources\CategoryResource;
use Modules\Shop\Repositories\Transaction\TransactionRepositoryInterface;

class TransactionController extends ApiAdminController
{

    /**
     * OutlayController constructor.
     * @param TransactionRepositoryInterface $repository
     */
    public function __construct(TransactionRepositoryInterface $repository)
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

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'transaction_id', 'order_id')

            ->resource(TransactionResource::class)
            ->json();
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'transaction_id','order_id'];
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
