<?php

namespace Modules\Admin\Http\Controllers\Api;


use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\Location\LocationRepositoryInterface;

class LocationController extends ApiAdminController
{
    /**
     * CategoryController constructor.
     * @param LocationRepositoryInterface $repository
     */
    public function __construct(LocationRepositoryInterface $repository)
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
        return ['id', 'name'];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
        ]);
    }
}
