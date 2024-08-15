<?php

namespace Modules\Admin\Http\Controllers\Api;


use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\Brand\BrandRepositoryInterface;

class BrandController extends ApiAdminController
{
    /**
     * CategoryController constructor.
     * @param BrandRepositoryInterface $repository
     */
    public function __construct(BrandRepositoryInterface $repository)
    {
        parent::__construct($repository);
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
        return ['id', 'name', 'slug'];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'slug' => 'required|max:255',
            'order' => 'required',
        ]);
    }
}
