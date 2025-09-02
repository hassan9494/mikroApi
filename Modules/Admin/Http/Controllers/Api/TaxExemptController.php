<?php

namespace Modules\Admin\Http\Controllers\Api;


use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\TaxExemptResource;
use Modules\Shop\Repositories\TaxExempt\TaxExemptRepositoryInterface;

class TaxExemptController extends ApiAdminController
{
    /**
     * CategoryController constructor.
     * @param TaxExemptRepositoryInterface $repository
     */
    public function __construct(TaxExemptRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @param $id
     * @return TaxExemptResource
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return new TaxExemptResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function select(): JsonResponse
    {
        $data = $this->repository->pluck('name');
        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'name', 'email'];
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

    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
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
            'email' => 'required|max:255',
            'identity_number_type' => 'required|max:255',
            'phone' => 'nullable|max:255',
            'tax_exempt' => 'sometimes|boolean',
            'tax_zero' => 'sometimes|boolean',
            'identity_number' => 'required|max:255',
            'user_id' => 'sometimes',
            'exemption_expiration_date' => 'required',
            'media' => 'nullable|array',
        ]);
    }

    public function autocomplete(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocomplete($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'identity_number_type' => $model->identity_number_type,
                'tax_exempt' => $model->tax_exempt,
                'tax_zero' => $model->tax_zero,
                'identity_number' => $model->identity_number,
                'user_id' => $model->user_id,
            ];
        }
        return $this->success($response);
    }
}
